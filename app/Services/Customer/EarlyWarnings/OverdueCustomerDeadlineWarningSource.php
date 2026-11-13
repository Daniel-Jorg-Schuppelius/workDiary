<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OverdueCustomerDeadlineWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\EarlyWarnings;

use App\Models\Customer\{Customer, CustomerDeadline};
use App\Models\Platform\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Frühwarnung (Feature 197, MVP-1633): Kunden mit überfälligen, noch offenen
 * Fristen. Die zuständige Person wurde schon über den Fristen-Scan erinnert;
 * die Warnung hält die Lage für die Leitung fest (keine zweite Meldung).
 */
final class OverdueCustomerDeadlineWarningSource implements EarlyWarningSource {
    public function key(): string {
        return 'customer_deadline_overdue';
    }

    public function warnings(Organization $organization): array {
        $rows = CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->open()
            ->where('due_on', '<', DateRange::day(CarbonImmutable::today()))
            ->selectRaw('customer_id, count(*) as overdue, min(due_on) as oldest')
            ->groupBy('customer_id')
            ->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $customers = Customer::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->whereIn('id', $rows->pluck('customer_id'))
            ->get()
            ->keyBy('id');

        $warnings = [];
        foreach ($rows as $row) {
            $customer = $customers->get((int) $row->getAttribute('customer_id'));
            if (! $customer instanceof Customer) {
                continue;
            }
            $warnings[] = new EarlyWarning(
                kind: $this->key(),
                subject: $customer,
                title: 'reporting.warning.customer_deadline_overdue.title',
                detail: 'reporting.warning.customer_deadline_overdue.detail',
                recommendation: 'reporting.warning.customer_deadline_overdue.recommendation',
                url: route('customer-deadlines.index', ['customer' => $customer->sqid, 'status' => 'overdue']),
                notify: false,
                params: [
                    'name' => (string) $customer->name,
                    'count' => (int) $row->getAttribute('overdue'),
                    'date' => CarbonImmutable::parse((string) $row->getAttribute('oldest'))->format('d.m.Y'),
                ],
            );
        }

        return $warnings;
    }
}
