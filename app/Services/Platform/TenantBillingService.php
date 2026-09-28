<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantBillingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Platform\{Organization, TenantUsageSnapshot};
use App\Services\Licensing\{LicenseService, ModuleStatusResolver};
use App\Services\Metrics\OperationsMetricsService;
use App\Settings\SettingsRegistry;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Collection;
use RoundingMode;

/**
 * Nutzungsabrechnung je Mandant (MVP-956): Monatsstand je Organisation als
 * Snapshot, bewertet mit den Einheitspreisen der Betreiber-Einstellungen
 * (`platform_billing.*`). Erzeugt keine Rechnung.
 */
class TenantBillingService {
    private const GIB = 1073741824;

    public function __construct(
        private readonly OperationsMetricsService $metrics,
        private readonly ModuleStatusResolver $modules,
        private readonly LicenseService $licenses,
        private readonly SettingsRegistry $settings,
    ) {}

    /** Stand des Monats für alle Organisationen ohne Demo; ein erneuter Lauf überschreibt. */
    public function snapshotAll(CarbonImmutable $month): int {
        $count = 0;
        foreach (Organization::query()->withoutGlobalScopes()->where('is_demo', false)->orderBy('id')->get() as $organization) {
            $this->snapshot($organization, $month);
            $count++;
        }

        return $count;
    }

    public function snapshot(Organization $organization, CarbonImmutable $month): TenantUsageSnapshot {
        $periodOn = $month->startOfMonth()->toDateString();
        $usage = $this->metrics->tenantUsage($organization);
        $users = (int) $organization->users()->withoutGlobalScopes()->whereNull('deactivated_at')->count();
        $modules = count(array_filter($this->modules->forOrganization($organization), static fn (array $row): bool => $row['available']));
        $license = $this->licenses->forOrganization($organization);
        $addons = $license->payload->addons ?? [];

        $snapshot = TenantUsageSnapshot::query()->where('organization_id', $organization->id)
            ->whereBetween('period_on', DateRange::days($periodOn, $periodOn))
            ->first() ?? new TenantUsageSnapshot(['organization_id' => $organization->id, 'period_on' => $periodOn]);
        $snapshot->fill([
            'plan' => (string) ($organization->plan ?? 'free'),
            'addons' => array_values($addons),
            'users' => $users,
            'active_users' => $usage['active_users'],
            'storage_bytes' => $usage['bytes'],
            'modules' => $modules,
            'amount' => $this->price($users, $usage['active_users'] ?? 0, $usage['bytes']),
            'currency' => (string) $this->setting('platform_billing.currency', 'EUR'),
            'computed_at' => now(),
        ])->save();

        return $snapshot;
    }

    /** @return numeric-string Grundgebühr + Nutzer + aktive Nutzer + angefangene GB */
    public function price(int $users, int $activeUsers, int $bytes): string {
        $gigabytes = (string) (int) ceil($bytes / self::GIB);
        $sum = $this->decimal('platform_billing.base_fee');
        $sum = bcadd($sum, bcmul((string) $users, $this->decimal('platform_billing.per_user'), 4), 4);
        $sum = bcadd($sum, bcmul((string) $activeUsers, $this->decimal('platform_billing.per_active_user'), 4), 4);
        $sum = bcadd($sum, bcmul($gigabytes, $this->decimal('platform_billing.per_gb'), 4), 4);

        return bcround($sum, 2, RoundingMode::HalfAwayFromZero);
    }

    /** @return Collection<int, TenantUsageSnapshot> */
    public function forMonth(CarbonImmutable $month): Collection {
        $periodOn = $month->startOfMonth()->toDateString();

        return TenantUsageSnapshot::query()->whereBetween('period_on', DateRange::days($periodOn, $periodOn))
            ->with('organization:id,name')
            ->orderBy('organization_id')
            ->get();
    }

    /** @return list<string> Monate mit Snapshots, neueste zuerst (Y-m) */
    public function months(): array {
        return array_values(array_unique(TenantUsageSnapshot::query()->orderByDesc('period_on')->pluck('period_on')
            ->map(static fn ($day): string => CarbonImmutable::parse((string) $day)->format('Y-m'))
            ->all()));
    }

    /** @return numeric-string */
    private function decimal(string $key): string {
        return NumberHelper::normalizeDecimalString((string) ($this->setting($key, '0') ?? '0'));
    }

    private function setting(string $key, mixed $default): mixed {
        return $this->settings->effective($key)->value ?? $default;
    }
}
