<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerReworkWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting\EarlyWarnings;

use App\Enums\Reporting\{ReportTargetMetric, ReportTargetScope};
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Services\Reporting\{EconomicsReportBuilder, ReportTargetEvaluator};
use Carbon\CarbonImmutable;

/**
 * Kunden, deren Nacharbeitsanteil der letzten 90 Tage den Zielwert deutlich
 * verfehlt (MVP-889). Ohne hinterlegten Zielwert keine Warnung — die
 * Schwelle legt der Betrieb fest, nicht der Code.
 */
final class CustomerReworkWarningSource implements EarlyWarningSource {
    /** Unter einer Stunde erfasster Zeit ist ein Anteil nicht aussagekräftig. */
    private const MIN_MINUTES = 60;

    public function __construct(
        private readonly EconomicsReportBuilder $economics,
        private readonly ReportTargetEvaluator $targets,
    ) {}

    public function key(): string {
        return 'customer-rework';
    }

    public function warnings(Organization $organization): array {
        $targets = $this->targets->load(ReportTargetMetric::ReworkShare);
        if ($targets->isEmpty()) {
            return [];
        }

        $to = CarbonImmutable::today();
        $warnings = [];
        // Aus Berichten ausgeblendete Kunden (Feature 002) warnen auch nicht.
        $excluded = array_values(Customer::query()->where('exclude_from_reports', true)->pluck('id')->map(static fn ($id): int => (int) $id)->all());
        foreach ($this->economics->byCustomer($to->subDays(90), $to, excludedCustomerIds: $excluded) as $row) {
            if ($row['totalMinutes'] < self::MIN_MINUTES) {
                continue;
            }
            $result = $this->targets->evaluate(ReportTargetMetric::ReworkShare, $this->targets->resolve($targets, ReportTargetScope::Customer, $row['customerId']), (float) $row['reworkShare']);
            $customer = Customer::query()->find($row['customerId']);
            if ($result === null || $result['tone'] !== 'error' || $customer === null) {
                continue;
            }
            $warnings[] = new EarlyWarning(
                kind: 'customer_rework',
                subject: $customer,
                title: 'reporting.warning.customer_rework.title',
                detail: 'reporting.warning.customer_rework.detail',
                recommendation: 'reporting.warning.customer_rework.recommendation',
                url: route('reports.economics', ['customer' => $customer->sqid]),
                params: ['name' => $row['customerName'], 'actual' => round((float) $row['reworkShare'], 1), 'target' => $result['target']],
            );
        }

        return $warnings;
    }
}
