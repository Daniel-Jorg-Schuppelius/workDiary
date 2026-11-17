<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadTimeKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting\Management;

use App\Enums\User\Permission;
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Services\Reporting\EntryTypeLeadTimeBuilder;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Durchlaufzeit je Auftragsart in der Leitungssicht (Feature 179, MVP-1670):
 * Median Anlegen bis Abschluss der in den letzten zwölf Monaten
 * abgeschlossenen Aufträge, für die häufigsten Auftragsarten — unabhängig vom
 * Zeitraum der Leitungssicht. Nachrechenbar in der Auftragstypanalyse
 * (Abschnitt „Durchlaufzeit“), nur mit deren Recht. Nie je Person.
 */
final class LeadTimeKpiSource implements ManagementKpiSource {
    /** Höchstzahl Auftragsarten in der Leitungssicht (die häufigsten). */
    public const int MAX_TYPES = 4;

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly EntryTypeLeadTimeBuilder $leadTimes,
    ) {}

    public function group(): string {
        return 'operations';
    }

    public function order(): int {
        return 60;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        // Recht der Auftragstypanalyse (RequiresTeamReportAccess): Admin oder `report.view`.
        if (! $this->features->routeEnabled('reports.entry-types') || ! ($viewer->isAdmin() || $viewer->can(Permission::ReportView->value))) {
            return [];
        }
        $until = CarbonImmutable::today();
        $since = $until->subMonthsNoOverflow(12)->addDay();
        $excluded = array_values(Customer::query()->where('organization_id', $organization->id)->where('exclude_from_reports', true)->pluck('id')->map(static fn ($id): int => (int) $id)->all());
        $rows = array_slice($this->leadTimes->build((int) $organization->id, $since, $until, $excluded), 0, self::MAX_TYPES);

        $kpis = [];
        foreach ($rows as $row) {
            $kpis[] = new ManagementKpi(
                __('reporting.management.kpi.lead_time', ['type' => $row['entryTypeName']]),
                trans_choice('reporting.management.kpi.lead_time_days', $row['medianDays'] === 1.0 ? 1 : 2, ['days' => NumberHelper::toGermanFormat($row['medianDays'], 1, trimTrailingZeros: true)]),
                __('reporting.management.kpi.lead_time_hint', ['count' => $row['completed']]),
                url: route('reports.entry-types', array_filter([
                    'from' => $since->toDateString(),
                    'to' => $until->toDateString(),
                    'entry_type_id' => $row['entryTypeId'] > 0 ? Sqid::encode(EntryType::class, $row['entryTypeId']) : null,
                ])) . '#lead-time',
            );
        }

        return $kpis;
    }
}
