<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\WorkQueue;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Ungelöste Service-Tickets, die dem Nutzer zugewiesen sind; Frist = Lösungs-, sonst Reaktionsfrist. */
final class ServiceTicketWorkItemSource implements WorkItemSource {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'service_ticket';
    }

    public function label(): string {
        return (string) __('work-queue.source.service_ticket');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return $this->features->routeEnabled('service-tickets.show') && Gate::forUser($viewer)->allows(Permission::ServiceTicketView->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $unresolved = array_map(
            static fn (ServiceTicketStatus $s): string => $s->value,
            array_values(array_filter(ServiceTicketStatus::cases(), static fn (ServiceTicketStatus $s): bool => ! $s->isResolved())),
        );
        $tickets = ServiceTicket::query()
            ->where('organization_id', $organization->id)
            ->where('assigned_to_user_id', $viewer->id)
            ->whereIn('status', $unresolved)
            ->orderBy('resolution_due_at')
            ->get();

        foreach ($tickets as $ticket) {
            // Vertrauliche Tickets prüft die Policy je Ticket.
            if (! $gate->allows('view', $ticket)) {
                continue;
            }
            $due = $ticket->resolution_due_at ?? $ticket->reaction_due_at;
            yield new WorkItem(
                source: $this->key(),
                key: $ticket->getMorphClass() . ':' . $ticket->id,
                title: $ticket->ticket_no . ' · ' . $ticket->title,
                url: route('service-tickets.show', $ticket),
                detail: $ticket->status->label(),
                dueAt: $due !== null ? CarbonImmutable::instance($due) : null,
                personal: true,
            );
        }
    }
}
