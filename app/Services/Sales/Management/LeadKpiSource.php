<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Management;

use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Services\Sales\LeadReport;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\Gate;

/** Lead-Zugang und Konversion im Zeitraum (Kohortensicht der Lead-Auswertung). */
final class LeadKpiSource implements ManagementKpiSource {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly LeadReport $leads,
    ) {}

    public function group(): string {
        return 'sales';
    }

    public function order(): int {
        return 10;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->features->routeEnabled('leads.report') || ! Gate::forUser($viewer)->allows(Permission::CustomerViewAny->value)) {
            return [];
        }
        $totals = $this->leads->build((int) $organization->id, $from, $to)['totals'];
        if ($totals['total'] === 0) {
            return [];
        }

        return [
            new ManagementKpi(
                __('reporting.management.kpi.new_leads'),
                (string) $totals['total'],
                $totals['rate'] !== null ? (string) __('reporting.management.kpi.conversion', ['value' => NumberHelper::toGermanFormat($totals['rate'], 1) . ' %', 'count' => $totals['converted']]) : null,
                url: route('leads.report', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
            ),
        ];
    }
}
