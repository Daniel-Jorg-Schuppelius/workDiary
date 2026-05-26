<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource, ServiceTicketStatus};
use App\Exceptions\ServiceTicketException;
use App\Models\{Organization, ServiceTicket, SlaContract, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServiceTicketService {
    public function __construct(
        private readonly TicketStatusMachine $statusMachine,
        private readonly SlaTimer $slaTimer,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(Organization $organization, User $actor, array $payload): ServiceTicket {
        $priority = $this->parsePriority((string) ($payload['priority'] ?? ServiceTicketPriority::Normal->value));
        $source = $this->parseSource((string) ($payload['source'] ?? ServiceTicketSource::Manual->value));
        $reportedAt = $this->parseDate($payload['reported_at'] ?? null) ?? Carbon::now();
        $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;

        $ticket = new ServiceTicket([
            'organization_id' => $organization->id,
            'ticket_no' => (string) ($payload['ticket_no'] ?? $this->generateTicketNo($organization)),
            'customer_id' => $customerId,
            'asset_id' => isset($payload['asset_id']) ? (int) $payload['asset_id'] : null,
            'project_id' => isset($payload['project_id']) ? (int) $payload['project_id'] : null,
            'title' => (string) ($payload['title'] ?? __('Neues Ticket')),
            'description' => $payload['description'] ?? null,
            'status' => ServiceTicketStatus::Reported->value,
            'priority' => $priority->value,
            'source' => $source->value,
            'source_reference' => $payload['source_reference'] ?? null,
            'reported_by_user_id' => $actor->id,
            'reported_at' => $reportedAt,
        ]);

        $contract = $this->slaTimer->resolveContract($organization->id, $customerId);
        if ($contract !== null) {
            $ticket->sla_contract_id = $contract->id;
            $deadlines = $this->slaTimer->computeDeadlines($contract, $priority, $reportedAt);
            $ticket->reaction_due_at = $deadlines['reaction_due_at'];
            $ticket->resolution_due_at = $deadlines['resolution_due_at'];
        }

        DB::transaction(function () use ($ticket): void {
            $ticket->save();
        });

        $ticket->audit('service_ticket.created', [
            'ticket_no' => $ticket->ticket_no,
            'priority' => $ticket->priority->value,
            'source' => $ticket->source->value,
        ]);

        return $ticket->refresh();
    }

    public function transition(ServiceTicket $ticket, User $actor, ServiceTicketStatus $to): ServiceTicket {
        $from = $ticket->status;
        if ($from === $to) {
            return $ticket;
        }
        $this->statusMachine->ensureTransition($from, $to);

        if ($to === ServiceTicketStatus::InProgress && $ticket->assigned_to_user_id === null) {
            throw ServiceTicketException::missingAssignee();
        }

        $now = Carbon::now();
        $ticket->status = $to;
        $this->stampTransition($ticket, $to, $now);

        DB::transaction(function () use ($ticket): void {
            $ticket->save();
        });

        $ticket->audit('service_ticket.status_changed', [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        return $ticket->refresh();
    }

    public function assign(ServiceTicket $ticket, User $actor, ?int $assigneeId): ServiceTicket {
        $previous = $ticket->assigned_to_user_id;
        $ticket->assigned_to_user_id = $assigneeId;

        DB::transaction(function () use ($ticket): void {
            $ticket->save();
        });

        $ticket->audit('service_ticket.assigned', [
            'from' => $previous,
            'to' => $assigneeId,
        ]);

        return $ticket->refresh();
    }

    public function attachSla(ServiceTicket $ticket, SlaContract $contract): ServiceTicket {
        $ticket->sla_contract_id = $contract->id;
        $reportedAt = $ticket->reported_at ?? Carbon::now();
        $deadlines = $this->slaTimer->computeDeadlines($contract, $ticket->priority, $reportedAt);
        $ticket->reaction_due_at = $deadlines['reaction_due_at'];
        $ticket->resolution_due_at = $deadlines['resolution_due_at'];
        $ticket->reaction_breached = false;
        $ticket->resolution_breached = false;

        DB::transaction(function () use ($ticket): void {
            $ticket->save();
        });

        $ticket->audit('service_ticket.sla_attached', [
            'sla_contract_id' => $contract->id,
        ]);

        return $ticket->refresh();
    }

    private function stampTransition(ServiceTicket $ticket, ServiceTicketStatus $to, Carbon $now): void {
        switch ($to) {
            case ServiceTicketStatus::Triaged:
            case ServiceTicketStatus::Scheduled:
                if ($ticket->acknowledged_at === null) {
                    $ticket->acknowledged_at = $now;
                }
                break;
            case ServiceTicketStatus::InProgress:
                if ($ticket->acknowledged_at === null) {
                    $ticket->acknowledged_at = $now;
                }
                if ($ticket->started_at === null) {
                    $ticket->started_at = $now;
                }
                break;
            case ServiceTicketStatus::Done:
                if ($ticket->resolved_at === null) {
                    $ticket->resolved_at = $now;
                }
                break;
            case ServiceTicketStatus::Accepted:
                $ticket->accepted_at = $now;
                if ($ticket->resolved_at === null) {
                    $ticket->resolved_at = $now;
                }
                break;
            case ServiceTicketStatus::Closed:
                $ticket->closed_at = $now;
                break;
            case ServiceTicketStatus::Rejected:
                $ticket->closed_at = $now;
                break;
            default:
                break;
        }
    }

    private function generateTicketNo(Organization $organization): string {
        $year = Carbon::now()->format('Y');
        $prefix = 'ST-' . $year . '-';
        $lastNo = ServiceTicket::query()
            ->where('organization_id', $organization->id)
            ->where('ticket_no', 'like', $prefix . '%')
            ->orderByDesc('ticket_no')
            ->value('ticket_no');

        $next = 1;
        if (is_string($lastNo)) {
            $tail = substr($lastNo, strlen($prefix));
            if (ctype_digit($tail)) {
                $next = (int) $tail + 1;
            }
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function parsePriority(string $value): ServiceTicketPriority {
        return ServiceTicketPriority::tryFrom($value) ?? ServiceTicketPriority::Normal;
    }

    private function parseSource(string $value): ServiceTicketSource {
        return ServiceTicketSource::tryFrom($value) ?? ServiceTicketSource::Manual;
    }

    private function parseDate(mixed $value): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }

        return Carbon::parse((string) $value);
    }
}
