<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Organization\TenantStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{RequiresPlatformOperator, ResolvesCurrentOrganization};
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, LicenseFlagOverride, Organization, User};
use App\Services\Licensing\{FeatureFlagResolver, LicenseResult, LicenseService, LicenseStatus, ModuleCatalog, ModuleStatusResolver};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LicenseAdminController extends Controller {
    use RequiresPlatformOperator;

    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly LicenseService $service,
        private readonly FeatureFlagResolver $resolver,
        private readonly ModuleStatusResolver $moduleStatus,
        private readonly ModuleCatalog $catalog,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::PlatformLicenseView->value);

        $result = $this->service->current($request->getHost());

        $orgCount = Organization::query()->count();

        // Org-gebundene Lizenz der aktuellen Organisation.
        $org = $request->user()?->organization;
        $orgLicense = $org !== null ? $this->service->forOrganization($org) : null;

        // Nutzer-Limit org-bezogen auswerten: maßgeblich ist die org-gebundene
        // Lizenz (sofern nutzbar), Auslastung sind die aktiven Org-Nutzer.
        $limitLicense = ($orgLicense !== null && $orgLicense->isUsable()) ? $orgLicense : $result;
        $userCount = $org !== null ? $org->activeUserCount() : User::query()->count();

        return view('admin.license.index', [
            'license' => $result,
            'badgeTone' => $this->badgeTone($result),
            'limits' => $this->limits($limitLicense, $userCount, $orgCount),
            'features' => $this->features($result),
            'expiresIn' => $this->expiresInDays($result),
            'isEnforced' => $this->service->isEnforced(),
            'canInstall' => $request->user()?->can(Permission::PlatformLicenseInstall->value) ?? false,
            'canToggleFlag' => $request->user()?->can(Permission::PlatformLicenseInstall->value) ?? false,
            'org' => $org,
            'orgLicense' => $orgLicense,
            'orgBadgeTone' => $orgLicense !== null ? $this->badgeTone($orgLicense) : 'neutral',
            'orgExpiresIn' => $orgLicense !== null ? $this->expiresInDays($orgLicense) : null,
            'orgModules' => $this->orgModules($orgLicense),
            'canIssue' => $this->service->canIssue(),
            'moduleCodes' => $this->moduleCodes(),
            // Mandantenstatus (trial/active/suspended/expired); abgeleitet aus
            // tenant_status-Spalte bzw. Lizenz-Ablauf (Feature 021).
            'tenantStatus' => $org?->tenantStatus($orgLicense),
            'tenantStatusExplicit' => $org?->tenant_status,
            'tenantStatusOptions' => TenantStatus::assignable(),
            'canManageTenant' => $request->user()?->can(Permission::PlatformLicenseInstall->value) ?? false,
            // MVP-052: org-bezogene Modulkonfiguration (4-Zustands-Modell).
            'modules' => $org !== null ? $this->moduleStatus->forOrganization($org) : [],
            'canConfigureModules' => $request->user()?->can(Permission::PlatformFeatureFlagOverride->value) ?? false,
        ]);
    }

    /**
     * Deaktiviert ein lizenziertes Modul für die EIGENE Organisation (MVP-052).
     * Keine Datenlöschung, keine Downgrade-Karenz — nur ein Disable-Override.
     * Org-Admin-Operation (`platform.featureFlag.override`), strikt org-gescopt.
     */
    public function disableModule(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformFeatureFlagOverride->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $data = $request->validate([
            'module' => ['required', 'string', Rule::in($this->catalog->codes())],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $module = (string) $data['module'];

        // Nur ein lizenziertes, aktuell aktives Modul darf deaktiviert werden.
        // Verhindert per Request-Manipulation das „Deaktivieren" nicht
        // lizenzierter Module (MVP-052 Akzeptanz 2).
        $status = $this->moduleStatus->statusFor($organization, $module);
        if (! $status->isConfigurable()) {
            return back()->with('error', __('Nur lizenzierte, aktive Module können deaktiviert werden.'));
        }

        $reason = isset($data['reason']) && trim((string) $data['reason']) !== '' ? trim((string) $data['reason']) : null;

        $existing = LicenseFlagOverride::query()
            ->where('organization_id', $organization->id)
            ->where('flag', $module)
            ->first();

        if ($existing === null) {
            LicenseFlagOverride::query()->create([
                'organization_id' => $organization->id,
                'flag' => $module,
                'reason' => $reason,
                'disabled_at' => CarbonImmutable::now(),
                'disabled_by_user_id' => $user->id,
            ]);
        }

        $this->auditModule($organization, $user, 'license.moduleDisabled', $module, $reason);
        $this->resolver->flush();

        return back()->with('success', __('Modul „:modul" deaktiviert. Es wurden keine Daten gelöscht.', [
            'modul' => $this->catalog->label($module),
        ]));
    }

    /**
     * Reaktiviert ein zuvor deaktiviertes Modul der EIGENEN Organisation
     * (MVP-052). Stellt Navigation und Zugriff ohne Datenmigration wieder her.
     */
    public function enableModule(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformFeatureFlagOverride->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $data = $request->validate([
            'module' => ['required', 'string', Rule::in($this->catalog->codes())],
        ]);
        $module = (string) $data['module'];

        // Org-Admin darf ausschließlich Overrides der eigenen Organisation
        // entfernen — plattformweite Overrides bleiben dem Plattform-Admin.
        $deleted = LicenseFlagOverride::query()
            ->where('organization_id', $organization->id)
            ->where('flag', $module)
            ->delete();

        if ($deleted > 0) {
            $this->auditModule($organization, $user, 'license.moduleEnabled', $module, null);
            $this->resolver->flush();
        }

        return back()->with('success', __('Modul „:modul" aktiviert.', [
            'modul' => $this->catalog->label($module),
        ]));
    }

    private function auditModule(Organization $organization, User $user, string $event, string $module, ?string $reason): void {
        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'module' => $module,
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Setzt den SaaS-Mandantenstatus der aktuellen Organisation (Feature 021).
     * `inherit` löscht den expliziten Wert und fällt auf die abgeleitete Logik
     * zurück. Plattform-Admin-Operation (Permission `platform.license.install`).
     */
    public function setTenantStatus(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformLicenseInstall->value);
        $this->assertPlatformOperator();

        /** @var User $user */
        $user = $request->user();
        $org = $user->organization;
        abort_if($org === null, Response::HTTP_NOT_FOUND);

        $data = $request->validate([
            'tenant_status' => ['required', Rule::in(['inherit', ...array_map(static fn(TenantStatus $s): string => $s->value, TenantStatus::assignable())])],
        ]);

        $previous = $org->tenant_status?->value;
        $org->tenant_status = $data['tenant_status'] === 'inherit'
            ? null
            : TenantStatus::from($data['tenant_status']);
        $org->save();
        $this->service->flushOrganization($org);

        AuditLog::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'event' => 'tenant.statusChanged',
            'auditable_type' => Organization::class,
            'auditable_id' => $org->id,
            'changes' => [
                'from' => $previous,
                'to' => $org->tenant_status?->value,
            ],
        ]);

        return back()->with('success', __('Mandantenstatus aktualisiert.'));
    }

    /**
     * Alle buchbaren Modul-Codes (module.*) aus dem Katalog – fuer die Add-on-Auswahl.
     *
     * @return list<string>
     */
    private function moduleCodes(): array {
        $codes = [];
        foreach ((array) config('plans.tiers.enterprise', []) as $code) {
            $code = (string) $code;
            if (str_starts_with($code, 'module.')) {
                $codes[] = $code;
            }
        }
        sort($codes);

        return $codes;
    }

    public function issuer(): View {
        Gate::authorize(Permission::PlatformLicenseInstall->value);
        $this->assertPlatformOperator();
        abort_unless($this->service->canIssue(), Response::HTTP_FORBIDDEN);

        return view('admin.license.issuer', [
            'moduleCodes' => $this->moduleCodes(),
        ]);
    }

    public function issueKey(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformLicenseInstall->value);
        $this->assertPlatformOperator();
        abort_unless($this->service->canIssue(), Response::HTTP_FORBIDDEN);

        $data = $request->validate([
            'licensee' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'plan' => ['required', \Illuminate\Validation\Rule::in(['free', 'pro', 'enterprise'])],
            'addons' => ['array'],
            'addons.*' => ['string', \Illuminate\Validation\Rule::in($this->moduleCodes())],
            'expires' => ['nullable', 'date'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'organization_uid' => ['nullable', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var list<string> $addons */
        $addons = array_values($data['addons'] ?? []);
        $key = $this->service->signLicense(
            (string) $data['plan'],
            $addons,
            $data['expires'] ?? null,
            (string) $data['licensee'],
            $data['organization_uid'] ?? null,
            $data['domain'] ?? null,
            $data['email'] ?? null,
            isset($data['max_users']) ? (int) $data['max_users'] : null,
        );

        if ($key === null) {
            return back()->withErrors(['issue' => __('Kein Private Key verfügbar.')])->withInput();
        }

        /** @var User $user */
        $user = $request->user();
        $organizationId = $this->currentOrganization()->id;
        AuditLog::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'event' => 'license.keyIssued',
            'auditable_type' => Organization::class,
            'auditable_id' => $organizationId,
            // Bewusst OHNE den Schlüssel selbst – nur Metadaten.
            'changes' => [
                'licensee' => $data['licensee'],
                'plan' => $data['plan'],
                'addons' => $addons,
                'organization' => $data['organization_uid'] ?? null,
                'domain' => $data['domain'] ?? null,
            ],
        ]);

        return back()
            ->with('issued_key', $key)
            ->with('issued_meta', [
                'licensee' => (string) $data['licensee'],
                'plan' => (string) $data['plan'],
            ])
            ->withInput();
    }

    public function issueOrg(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformLicenseInstall->value);
        $this->assertPlatformOperator();

        /** @var User $user */
        $user = $request->user();
        $org = $user->organization;
        abort_if($org === null, Response::HTTP_NOT_FOUND);
        abort_unless($this->service->canIssue(), Response::HTTP_FORBIDDEN);

        $data = $request->validate([
            'licensee' => ['required', 'string', 'max:255'],
            'plan' => ['required', \Illuminate\Validation\Rule::in(['free', 'pro', 'enterprise'])],
            'addons' => ['array'],
            'addons.*' => ['string', \Illuminate\Validation\Rule::in($this->moduleCodes())],
            'expires' => ['nullable', 'date'],
        ]);

        /** @var list<string> $addons */
        $addons = array_values($data['addons'] ?? []);
        $result = $this->service->issueForOrganization(
            $org,
            (string) $data['plan'],
            $addons,
            $data['expires'] ?? null,
            (string) $data['licensee'],
        );
        $this->resolver->flush();

        if (! $result->isUsable()) {
            return back()->withErrors(['issue' => __('Lizenz konnte nicht erstellt werden: ') . ($result->message ?? $result->status->value)]);
        }

        AuditLog::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'event' => 'license.orgIssued',
            'auditable_type' => Organization::class,
            'auditable_id' => $org->id,
            'changes' => ['license_id' => $result->payload?->licenseId, 'plan' => $result->payload?->plan, 'addons' => $addons],
        ]);

        return back()->with('success', __('Lizenz erstellt & installiert. Plan: ') . __('values.' . (string) $data['plan']));
    }

    /**
     * Tier + einzeln gebuchte Module der Org-Lizenz fuer die Anzeige.
     *
     * @return array{plan:string, addons:list<string>}
     */
    private function orgModules(?LicenseResult $orgLicense): array {
        $payload = $orgLicense?->payload;
        if ($payload === null || ! $orgLicense->isUsable()) {
            return ['plan' => 'free', 'addons' => []];
        }

        return [
            'plan' => $payload->plan,
            'addons' => array_values(array_map(static fn($v): string => (string) $v, $payload->addons)),
        ];
    }

    public function installOrg(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformLicenseInstall->value);
        $this->assertPlatformOperator();

        /** @var User $user */
        $user = $request->user();
        $org = $user->organization;
        abort_if($org === null, Response::HTTP_NOT_FOUND);

        $data = $request->validate(['license_key' => ['required', 'string', 'max:8000']]);
        $result = $this->service->installForOrganization($org, $data['license_key']);
        $this->resolver->flush();

        if (! $result->isUsable()) {
            return back()->withErrors([
                'license_key' => __('Lizenz konnte nicht installiert werden: ') . ($result->message ?? $result->status->value),
            ]);
        }

        AuditLog::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'event' => 'license.orgInstalled',
            'auditable_type' => Organization::class,
            'auditable_id' => $org->id,
            'changes' => ['license_id' => $result->payload?->licenseId, 'plan' => $result->payload?->plan],
        ]);

        $plan = $result->payload !== null ? __('values.' . $result->payload->plan) : '—';

        return back()->with('success', __('Org-Lizenz installiert. Plan: ') . $plan);
    }

    public function removeOrg(Request $request): RedirectResponse {
        Gate::authorize(Permission::PlatformLicenseInstall->value);
        $this->assertPlatformOperator();

        /** @var User $user */
        $user = $request->user();
        $org = $user->organization;
        abort_if($org === null, Response::HTTP_NOT_FOUND);

        $org->forceFill(['license_key' => null])->save();
        $this->service->flushOrganization($org);
        $this->resolver->flush();

        AuditLog::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'event' => 'license.orgRemoved',
            'auditable_type' => Organization::class,
            'auditable_id' => $org->id,
            'changes' => [],
        ]);

        return back()->with('success', __('Org-Lizenz entfernt.'));
    }

    /**
     * Einzelnes Lizenz-Flag der **eigenen** Organisation abschalten.
     *
     * Org-gescopt wie {@see disableModule()} — trotz des Rechts
     * `platform.license.install`, das hier historisch gewählt wurde. Deshalb
     * keine Betreiber-Schranke (Sicherheitsscan 2026-08-23, S-02).
     */
    public function toggleFlag(Request $request, string $flag): RedirectResponse {
        Gate::authorize(Permission::PlatformLicenseInstall->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $result = $this->service->current($request->getHost());
        $payload = $result->payload;
        $licensedFlags = $payload !== null
            ? array_map(static fn($v): string => (string) $v, $payload->features)
            : [];

        if (! in_array($flag, $licensedFlags, true)) {
            return back()->withErrors([
                'flag' => __('Nur lizenzierte Features können lokal deaktiviert werden.'),
            ]);
        }

        $existing = LicenseFlagOverride::query()
            ->where('organization_id', $organization->id)
            ->where('flag', $flag)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $event = 'license.flagRestored';
            $reason = (string) $existing->reason;
        } else {
            $reason = (string) $request->input('reason', '');
            LicenseFlagOverride::query()->create([
                'organization_id' => $organization->id,
                'flag' => $flag,
                'reason' => $reason !== '' ? $reason : null,
                'disabled_at' => CarbonImmutable::now(),
                'disabled_by_user_id' => $user->id,
            ]);
            $event = 'license.flagDisabled';
        }

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => [
                'flag' => $flag,
                'reason' => $reason !== '' ? $reason : null,
            ],
        ]);

        $this->resolver->flush();
        $this->service->flush();

        return back()->with('success', $event === 'license.flagDisabled'
            ? __('Feature lokal deaktiviert.')
            : __('Lokaler Override entfernt.'));
    }

    /**
     * @return list<array{
     *     code:string, label:string, used:int|null, max:int|null,
     *     percent:int|null, status:string,
     * }>
     */
    private function limits(LicenseResult $result, int $userCount, int $orgCount): array {
        $payload = $result->payload;
        $rows = [];

        $rows[] = $this->limitRow('users', __('Nutzer'), $userCount, $payload?->maxUsers);
        $rows[] = $this->limitRow('organizations', __('Organisationen'), $orgCount, null);

        return $rows;
    }

    /**
     * @return array{code:string, label:string, used:int|null, max:int|null, percent:int|null, status:string}
     */
    private function limitRow(string $code, string $label, ?int $used, ?int $max): array {
        $percent = null;
        $status = 'ok';

        if ($max !== null && $max > 0 && $used !== null) {
            $percent = (int) round(($used / $max) * 100);
            $status = match (true) {
                $percent >= 100 => 'critical',
                $percent >= 80 => 'warn',
                default => 'ok',
            };
        }

        return [
            'code' => $code,
            'label' => $label,
            'used' => $used,
            'max' => $max,
            'percent' => $percent,
            'status' => $status,
        ];
    }

    /** @return list<array{code:string, enabled:bool, source:string, overridden:bool}> */
    private function features(LicenseResult $result): array {
        $payload = $result->payload;
        if ($payload === null) {
            return [];
        }

        $disabled = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('license_flag_overrides')) {
            $disabled = LicenseFlagOverride::query()->pluck('flag')->all();
        }

        $out = [];
        foreach ($payload->features as $code) {
            $code = (string) $code;
            if ($code === '') {
                continue;
            }
            $overridden = in_array($code, $disabled, true);
            $out[] = [
                'code' => $code,
                'enabled' => ! $overridden,
                'source' => $overridden ? 'override' : 'license',
                'overridden' => $overridden,
            ];
        }
        usort($out, static fn(array $a, array $b): int => $a['code'] <=> $b['code']);

        return $out;
    }

    private function badgeTone(LicenseResult $result): string {
        return match ($result->status) {
            LicenseStatus::Valid => 'success',
            LicenseStatus::GracePeriod => 'warning',
            LicenseStatus::Missing, LicenseStatus::Expired, LicenseStatus::Malformed,
            LicenseStatus::BadSignature, LicenseStatus::DomainMismatch, LicenseStatus::OrgMismatch,
            LicenseStatus::PublicKeyMissing, LicenseStatus::Tampered => 'error',
        };
    }

    private function expiresInDays(LicenseResult $result): ?int {
        $expires = $result->payload?->expiresAt;
        if ($expires === null) {
            return null;
        }

        return (int) CarbonImmutable::now()->diffInDays($expires, false);
    }
}
