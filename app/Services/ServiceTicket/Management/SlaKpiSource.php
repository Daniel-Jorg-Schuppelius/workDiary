<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Management;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\{ServiceTicket, SlaViolation};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Offene Tickets und SLA-Verletzungen im Zeitraum — gezählt wie der SLA-Bericht
 * ohne Filter (Verletzungen an Tickets ausgeblendeter Kunden entfallen).
 */
final class SlaKpiSource implements ManagementKpiSource {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function group(): string {
        return 'service';
    }

    public function order(): int {
        return 10;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        if (! $this->features->routeEnabled('reports.sla') || ! Gate::forUser($viewer)->allows('viewAny', SlaViolation::class)) {
            return [];
        }
        $excluded = Customer::query()->where('organization_id', $organization->id)->where('exclude_from_reports', true)->pluck('id')->all();
        $violations = SlaViolation::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('breached_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($excluded !== [], fn ($q) => $q->whereDoesntHave('serviceTicket', fn ($t) => $t->whereIn('customer_id', $excluded)))
            ->count();
        $unresolved = array_map(
            static fn (ServiceTicketStatus $s): string => $s->value,
            array_values(array_filter(ServiceTicketStatus::cases(), static fn (ServiceTicketStatus $s): bool => ! $s->isResolved())),
        );
        $open = ServiceTicket::query()->where('organization_id', $organization->id)->whereIn('status', $unresolved)->count();

        return [
            new ManagementKpi(__('cockpit.tickets.title'), (string) $open, url: route('service-tickets.index')),
            new ManagementKpi(
                __('reporting.management.kpi.sla_violations'),
                (string) $violations,
                tone: $violations > 0 ? 'warning' : 'success',
                url: route('reports.sla', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
            ),
        ];
    }
}
