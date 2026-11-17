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

use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Delegation\Contracts\DelegableWork;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Ungelöste Service-Tickets, die dem Nutzer zugewiesen sind; Frist = Lösungs-,
 * sonst Reaktionsfrist. Zugleich delegierbare Arbeit (Feature 202).
 */
final class ServiceTicketWorkItemSource implements DelegableWork, WorkItemSource {
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
        $tickets = $this->assigned($viewer, (int) $organization->id)
            // Liegezeit (MVP-1670): letzte Zeitbuchung neben der eigenen Änderung (Status, Antwort).
            ->withMax('timeEntries', 'created_at')
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
                movedAt: WorkItem::lastMoved($ticket->updated_at, $ticket->getAttribute('time_entries_max_created_at')),
            );
        }
    }

    public function count(User $user): int {
        return $this->assigned($user, (int) $user->organization_id)->count();
    }

    /** @return Builder<ServiceTicket> */
    private function assigned(User $user, int $organizationId): Builder {
        return ServiceTicket::query()
            ->where('organization_id', $organizationId)
            ->where('assigned_to_user_id', $user->id)
            ->open();
    }
}
