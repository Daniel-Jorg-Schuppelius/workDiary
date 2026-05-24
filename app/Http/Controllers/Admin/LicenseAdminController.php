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
use App\Models\{Organization, User};
use App\Services\Licensing\{LicenseResult, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LicenseAdminController extends Controller {
    public function __construct(private readonly LicenseService $service) {}

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
        ]);
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

    /** @return list<array{code:string, enabled:bool, source:string}> */
    private function features(LicenseResult $result): array {
        $payload = $result->payload;
        if ($payload === null) {
            return [];
        }

        $out = [];
        foreach ($payload->features as $code => $enabled) {
            $out[] = [
                'code' => (string) $code,
                'enabled' => (bool) $enabled,
                'source' => 'license',
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
