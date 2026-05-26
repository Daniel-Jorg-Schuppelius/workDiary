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

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, LicenseFlagOverride, Organization, User};
use App\Services\Licensing\{FeatureFlagResolver, LicenseResult, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LicenseAdminController extends Controller {
    public function __construct(
        private readonly LicenseService $service,
        private readonly FeatureFlagResolver $resolver,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::PlatformLicenseView->value);

        $result = $this->service->current($request->getHost());

        $userCount = User::query()->count();
        $orgCount = Organization::query()->count();

        return view('admin.license.index', [
            'license' => $result,
            'badgeTone' => $this->badgeTone($result),
            'limits' => $this->limits($result, $userCount, $orgCount),
            'features' => $this->features($result),
            'expiresIn' => $this->expiresInDays($result),
            'isEnforced' => $this->service->isEnforced(),
            'canInstall' => $request->user()?->can(Permission::PlatformLicenseInstall->value) ?? false,
            'canToggleFlag' => $request->user()?->can(Permission::PlatformLicenseInstall->value) ?? false,
        ]);
    }

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
            LicenseStatus::BadSignature, LicenseStatus::DomainMismatch,
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
