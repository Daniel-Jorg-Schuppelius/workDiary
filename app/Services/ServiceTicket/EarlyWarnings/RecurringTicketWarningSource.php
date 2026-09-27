<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringTicketWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\EarlyWarnings;

use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Support\Setting;

/**
 * Wiederkehrende Probleme (MVP-926): Kunden bzw. Objekte mit auffällig vielen
 * Tickets im Zeitfenster. Schwelle und Fenster sind Einstellungen der
 * Organisation, nicht im Code festgelegt.
 */
final class RecurringTicketWarningSource implements EarlyWarningSource {
    public function key(): string {
        return 'recurring-tickets';
    }

    public function warnings(Organization $organization): array {
        $threshold = max(2, (int) Setting::get('reporting.recurring_tickets.threshold', 3));
        $days = max(7, (int) Setting::get('reporting.recurring_tickets.window_days', 90));
        $since = now()->subDays($days);

        $warnings = [];
        $groups = ServiceTicket::query()
            ->where('organization_id', $organization->id)
            ->where('reported_at', '>=', $since)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, asset_id, count(*) as tickets')
            ->groupBy('customer_id', 'asset_id')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->orderByDesc('tickets')
            ->limit(20)
            ->get();
        foreach ($groups as $group) {
            $asset = $group->asset_id !== null ? Asset::query()->find($group->asset_id) : null;
            $customer = Customer::query()->find($group->customer_id);
            $subject = $asset ?? $customer;
            if ($subject === null) {
                continue;
            }
            $warnings[] = new EarlyWarning(
                kind: 'recurring_tickets',
                subject: $subject,
                title: 'reporting.warning.recurring_tickets.title',
                detail: 'reporting.warning.recurring_tickets.detail',
                recommendation: 'reporting.warning.recurring_tickets.recommendation',
                url: $asset !== null ? route('assets.show', $asset) : route('customers.show', $customer),
                params: ['name' => $asset !== null ? $asset->name . ' (' . ($customer->name ?? '—') . ')' : (string) $customer?->name, 'count' => (int) $group->getAttribute('tickets'), 'days' => $days],
            );
        }

        return $warnings;
    }
}
