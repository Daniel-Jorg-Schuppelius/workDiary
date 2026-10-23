<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UtilizationKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Management;

use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Services\Reporting\UtilizationReportBuilder;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\Gate;

/** Auslastung und abrechenbarer Anteil aller aktiven Personen — wie die Auslastungsauswertung ohne Filter. */
final class UtilizationKpiSource implements ManagementKpiSource {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly UtilizationReportBuilder $utilization,
    ) {}

    public function group(): string {
        return 'operations';
    }

    public function order(): int {
        return 20;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->features->routeEnabled('reports.utilization') || ! ($viewer->isAdmin() || Gate::forUser($viewer)->allows('viewAny', User::class))) {
            return [];
        }
        $users = User::query()->where('organization_id', $organization->id)->whereNull('deactivated_at')->orderBy('name')->get();
        $totals = $this->utilization->build($from, $to, $users)['totals'];
        if ($totals['utilization'] === null) {
            return [];
        }
        $pct = static fn (?float $v): string => $v === null ? '—' : NumberHelper::toGermanFormat($v, 1) . ' %';

        return [
            new ManagementKpi(
                __('Auslastung gesamt'),
                $pct($totals['utilization']),
                __('reporting.management.kpi.billable_rate', ['value' => $pct($totals['billableRate'])]),
                url: route('reports.utilization', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
            ),
        ];
    }
}
