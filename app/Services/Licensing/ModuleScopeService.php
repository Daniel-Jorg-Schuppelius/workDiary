<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleScopeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Licensing;

use App\Enums\Licensing\ModuleStatus;
use App\Models\{AuditLog, LicenseFlagOverride, Organization, User};
use Carbon\CarbonImmutable;

/**
 * Funktionsumfang der Organisation (Feature 081, MVP-373).
 *
 * Presets und die Seite „Funktionsumfang" sind reine Schreibhilfen für die
 * vorhandene Modulkonfiguration (MVP-052): Es wird ausschließlich der
 * Modulstatus über {@see LicenseFlagOverride} geschrieben (nur deaktivierend,
 * Option A) — nie lizenzerweiternd, nie datenlöschend, immer auditiert und
 * idempotent. Nicht lizenzierte oder systemseitig gesperrte Module bleiben
 * unberührt (D15).
 */
class ModuleScopeService {
    public function __construct(
        private readonly ModuleStatusResolver $status,
        private readonly ModuleCatalog $catalog,
        private readonly FeatureFlagResolver $resolver,
    ) {}

    /**
     * Kuratierte Presets aus config/plans.php (validierte Form).
     *
     * @return array<string, array{label: string, description: string, modules: list<string>|null}>
     */
    public function presets(): array {
        $out = [];
        foreach ((array) config('plans.presets', []) as $key => $preset) {
            if (! is_string($key) || $key === '' || ! is_array($preset)) {
                continue;
            }
            $modules = $preset['modules'] ?? null;
            $out[$key] = [
                'label' => (string) ($preset['label'] ?? $key),
                'description' => (string) ($preset['description'] ?? ''),
                'modules' => is_array($modules)
                    ? array_values(array_filter(array_map(static fn($m): string => (string) $m, $modules), fn(string $m): bool => $this->catalog->has($m)))
                    : null,
            ];
        }

        return $out;
    }

    /**
     * Modul-Empfehlung des installierten Branchenprofils der Organisation
     * (`modules_recommended` im Profil-Array), auf Katalogmodule gefiltert.
     * null = kein Profil installiert oder keine Empfehlung hinterlegt.
     *
     * @return array{code: string, label: string, modules: list<string>}|null
     */
    public function branchProfileRecommendation(Organization $organization): ?array {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $code = (string) ($settings['branch_profile_code'] ?? '');
        if ($code === '' || ! preg_match('/^[a-z0-9\-]+$/', $code)) {
            return null;
        }

        $path = database_path("data/branchprofiles/{$code}.php");
        if (! is_file($path)) {
            return null;
        }

        /** @var array<string, mixed> $profile */
        $profile = require $path;
        $recommended = $profile['modules_recommended'] ?? null;
        if (! is_array($recommended) || $recommended === []) {
            return null;
        }

        $modules = array_values(array_filter(
            array_map(static fn($m): string => (string) $m, $recommended),
            fn(string $m): bool => $this->catalog->has($m)
        ));
        if ($modules === []) {
            return null;
        }

        return [
            'code' => $code,
            'label' => (string) ($profile['label'] ?? $code),
            'modules' => $modules,
        ];
    }

    /**
     * Wendet ein Preset an (Schreibhilfe für {@see setActiveModules}).
     *
     * @return array{disabled: list<string>, enabled: list<string>}
     */
    public function applyPreset(Organization $organization, string $presetKey, User $actor): array {
        $presets = $this->presets();
        if (! isset($presets[$presetKey])) {
            throw new \InvalidArgumentException("Unbekanntes Funktionsumfang-Preset: {$presetKey}");
        }

        return $this->setActiveModules($organization, $presets[$presetKey]['modules'], $actor, $presetKey);
    }

    /**
     * Setzt den aktiven Modulumfang der Organisation idempotent.
     *
     * @param  list<string>|null  $activeModules  Aktiv bleibende Katalogmodule;
     *                                            null = alle lizenzierten aktiv.
     * @return array{disabled: list<string>, enabled: list<string>}
     */
    public function setActiveModules(Organization $organization, ?array $activeModules, User $actor, ?string $presetKey = null): array {
        $active = $activeModules === null
            ? null
            : array_values(array_filter(array_map(static fn($m): string => (string) $m, $activeModules), fn(string $m): bool => $this->catalog->has($m)));

        $disabled = [];
        $enabled = [];
        $reason = $presetKey !== null ? ('scope:' . $presetKey) : 'scope';

        foreach ($this->status->forOrganization($organization) as $row) {
            $code = $row['code'];
            $shouldBeActive = $active === null || in_array($code, $active, true);

            if ($row['status'] === ModuleStatus::Active && ! $shouldBeActive) {
                // Nur lizenzierte, aktive Module deaktivieren (MVP-052 Akzeptanz 2).
                LicenseFlagOverride::query()->firstOrCreate(
                    ['organization_id' => $organization->id, 'flag' => $code],
                    [
                        'reason' => $reason,
                        'disabled_at' => CarbonImmutable::now(),
                        'disabled_by_user_id' => $actor->id,
                    ]
                );
                $this->auditModule($organization, $actor, 'license.moduleDisabled', $code, $reason);
                $disabled[] = $code;

                continue;
            }

            if ($row['status'] === ModuleStatus::InactiveByCustomer && $shouldBeActive) {
                // Nur org-eigene Overrides entfernen — plattformweite bleiben
                // dem Plattform-Admin vorbehalten (wie LicenseAdminController).
                $deleted = LicenseFlagOverride::query()
                    ->where('organization_id', $organization->id)
                    ->where('flag', $code)
                    ->delete();
                if ($deleted > 0) {
                    $this->auditModule($organization, $actor, 'license.moduleEnabled', $code, null);
                    $enabled[] = $code;
                }
            }
            // NotLicensed/Blocked: unberührt — ein Preset schaltet nie frei (Option A).
        }

        if ($disabled !== [] || $enabled !== [] || $presetKey !== null) {
            AuditLog::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $actor->id,
                'event' => 'license.scopeConfigured',
                'auditable_type' => Organization::class,
                'auditable_id' => $organization->id,
                'changes' => [
                    'preset' => $presetKey,
                    'disabled' => $disabled,
                    'enabled' => $enabled,
                ],
            ]);

            // Onboarding-Schritt `org.scope` (Feature 081): bewusste
            // Umfang-Entscheidung dokumentieren (auch „Voller Umfang").
            $settings = is_array($organization->settings) ? $organization->settings : [];
            $settings['scope_configured_at'] = CarbonImmutable::now()->toIso8601String();
            $organization->settings = $settings;
            $organization->save();
        }

        $this->resolver->flush();

        return ['disabled' => $disabled, 'enabled' => $enabled];
    }

    private function auditModule(Organization $organization, User $actor, string $event, string $module, ?string $reason): void {
        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'event' => $event,
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'module' => $module,
                'reason' => $reason,
            ],
        ]);
    }
}
