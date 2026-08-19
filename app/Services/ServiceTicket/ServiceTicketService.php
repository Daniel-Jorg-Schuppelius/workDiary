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

use App\Enums\Numbering\NumberScope;
use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource, ServiceTicketStatus};
use App\Exceptions\ServiceTicketException;
use App\Models\{DiaryEntry, Organization, ServiceQueue, ServiceTicket, SlaClockSegment, SlaContract, User};
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServiceTicketService {
    public function __construct(
        private readonly TicketStatusMachine $statusMachine,
        private readonly SlaTimer $slaTimer,
        private readonly NumberSequenceService $numberSequence,
        private readonly SlaViolationService $slaViolations = new SlaViolationService,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(Organization $organization, ?User $actor, array $payload): ServiceTicket {
        $priority = $this->parsePriority((string) ($payload['priority'] ?? ServiceTicketPriority::Normal->value));
        $source = $this->parseSource((string) ($payload['source'] ?? ServiceTicketSource::Manual->value));
        $reportedAt = $this->parseDate($payload['reported_at'] ?? null) ?? Carbon::now();
        $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;
        $projectId = isset($payload['project_id']) ? (int) $payload['project_id'] : null;

        // Auftragsbezug (Feature 010): Kunde/Projekt aus dem org-gescopten Eintrag vorbefüllen, wenn nicht gesetzt.
        $diaryEntryId = isset($payload['diary_entry_id']) ? (int) $payload['diary_entry_id'] : null;
        if ($diaryEntryId !== null) {
            $entry = DiaryEntry::query()
                ->where('organization_id', $organization->id)
                ->whereKey($diaryEntryId)
                ->first(['id', 'customer_id', 'project_id']);
            if ($entry === null) {
                $diaryEntryId = null; // fremder/unbekannter Eintrag → keine Verknüpfung
            } else {
                $customerId ??= $entry->customer_id !== null ? (int) $entry->customer_id : null;
                $projectId ??= $entry->project_id !== null ? (int) $entry->project_id : null;
            }
        }

        // Queue (Feature 065): explizit oder Default-Queue der Org.
        $queueId = isset($payload['queue_id'])
            ? (int) $payload['queue_id']
            : \App\Models\ServiceQueue::query()
                ->where('organization_id', $organization->id)
                ->where('is_default', true)
                ->value('id');

        $ticket = new ServiceTicket([
            'organization_id' => $organization->id,
            'queue_id' => $queueId,
            'kind' => (string) ($payload['kind'] ?? \App\Enums\ServiceTicket\ServiceTicketKind::Incident->value),
            'ticket_no' => (string) ($payload['ticket_no'] ?? $this->generateTicketNo($organization, $reportedAt)),
            'customer_id' => $customerId,
            'asset_id' => isset($payload['asset_id']) ? (int) $payload['asset_id'] : null,
            'project_id' => $projectId,
            'diary_entry_id' => $diaryEntryId,
            'title' => (string) ($payload['title'] ?? __('Neues Ticket')),
            'description' => $payload['description'] ?? null,
            'status' => ServiceTicketStatus::Reported->value,
            'priority' => $priority->value,
            'source' => $source->value,
            'source_reference' => $payload['source_reference'] ?? null,
            'reported_by_user_id' => $actor?->id,
            'reported_at' => $reportedAt,
        ]);

        // Expliziter (org-gescopter) Vertrag aus dem Payload gewinnt, sonst Auflösung
        // Projekt → Kunde → Org-Default (Rang 43; Projektbindung W5.4).
        $contract = $this->resolveExplicitContract($organization, $payload)
            ?? $this->slaTimer->resolveContract($organization->id, $customerId, $projectId);
        if ($contract !== null) {
            $ticket->sla_contract_id = $contract->id;
            $deadlines = $this->slaTimer->computeDeadlines($contract, $priority, $reportedAt);
            $ticket->reaction_due_at = $deadlines['reaction_due_at'];
            $ticket->resolution_due_at = $deadlines['resolution_due_at'];
            // Vertragsstand einfrieren (Feature 065, P3): spätere Vertragsänderungen deuten bestehende Tickets nie um (DoD).
            $ticket->sla_snapshot = [
                'contract_id' => $contract->id,
                'contract_name' => $contract->label,
                'priority_table' => $contract->priority_table,
                'business_hours' => $contract->business_hours,
                'pause_rules' => $contract->pause_rules,
                'frozen_at' => $reportedAt->toIso8601String(),
            ];
        }

        DB::transaction(function () use ($ticket): void {
            $ticket->save();
        });

        $ticket->audit('service_ticket.created', [
            'ticket_no' => $ticket->ticket_no,
            'priority' => $ticket->priority->value,
            'source' => $ticket->source->value,
        ]);

        // Routing-Regeln (Feature 065, P3): deterministisch, protokolliert.
        app(TicketRoutingService::class)->apply($ticket);

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
        $wasAcknowledged = $ticket->acknowledged_at !== null;
        $wasResolved = $ticket->resolved_at !== null;
        $ticket->status = $to;
        $this->stampTransition($ticket, $to, $now);

        DB::transaction(function () use ($ticket): void {
            $ticket->save();
        });

        $ticket->audit('service_ticket.status_changed', [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        // SLA-Verletzungsregister (Feature 010): je Ticket+Typ genau eine Violation (idempotent).
        if (! $wasAcknowledged && $ticket->acknowledged_at !== null
            && $ticket->reaction_due_at !== null && $ticket->acknowledged_at->greaterThan($ticket->reaction_due_at)) {
            $this->slaViolations->recordResponseBreach($ticket);
        }
        if (! $wasResolved && $ticket->resolved_at !== null
            && $ticket->resolution_due_at !== null && $ticket->resolved_at->greaterThan($ticket->resolution_due_at)) {
            $this->slaViolations->recordResolutionBreach($ticket);
        }

        // Umfrage-Trigger (Feature 090): Fragebögen mit trigger_on_ticket_close
        // laden nach der ERSTEN Lösung ein. Der Ermüdungsschutz überspringt
        // still - ein gescheiterter Einladungsversuch darf nie den
        // Statuswechsel des Tickets verhindern.
        if (! $wasResolved && $ticket->resolved_at !== null) {
            app(\App\Services\Survey\SurveyTicketTrigger::class)->onTicketResolved($ticket);
        }

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

        // ticket.assigned (Feature 065, P3): nur bei echtem Wechsel auf eine Person, dedupliziert.
        if ($assigneeId !== null && $assigneeId !== $previous) {
            $assignee = User::query()->find($assigneeId);
            if ($assignee !== null) {
                app(\App\Services\Notification\NotificationDispatcher::class)->notify(
                    \App\Enums\Notification\NotificationEvent::TicketAssigned,
                    $ticket,
                    $assignee,
                    [
                        'title' => (string) __('Ticket :no zugewiesen', ['no' => $ticket->ticket_no]),
                        'title_key' => 'Ticket :no zugewiesen',
                        'title_params' => ['no' => $ticket->ticket_no],
                        'body' => (string) $ticket->title,
                        'url' => route('service-tickets.show', $ticket),
                    ],
                    dedup: true,
                );
            }
        }

        return $ticket->refresh();
    }

    /**
     * Queue-Wechsel (Feature 065, MVP-160): harte Tenant-Grenze, idempotent.
     * Audit-Event `service_ticket.requeued` ist Datenbasis für MVP-159 — Event-Name nicht ändern.
     */
    public function moveToQueue(ServiceTicket $ticket, User $actor, ServiceQueue $queue): ServiceTicket {
        if ((int) $queue->organization_id !== (int) $ticket->organization_id) {
            throw new \RuntimeException((string) __('Queue-Wechsel über Organisationsgrenzen ist nicht zulässig.'));
        }

        $previous = $ticket->queue_id !== null ? (int) $ticket->queue_id : null;
        if ($previous === (int) $queue->id) {
            return $ticket;
        }

        $ticket->queue()->associate($queue);

        DB::transaction(function () use ($ticket): void {
            $ticket->save();
        });

        $ticket->audit('service_ticket.requeued', [
            'from' => $previous,
            'to' => (int) $queue->id,
            'actor' => $actor->id,
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

    /**
     * Wartezustand (Feature 065, P1): Grund + Wiedervorlage Pflicht; SLA-Uhr pausiert
     * nur, wenn der Vertrag den Zielstatus in pause_rules deklariert.
     */
    public function wait(ServiceTicket $ticket, User $actor, ServiceTicketStatus $to, string $reason, \DateTimeInterface $until, ?int $ownerId = null): ServiceTicket {
        if (! $to->isWaiting()) {
            throw new \InvalidArgumentException('Zielstatus ist kein Wartezustand.');
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException((string) __('Wartezustand braucht einen Grund.'));
        }

        return DB::transaction(function () use ($ticket, $actor, $to, $reason, $until, $ownerId): ServiceTicket {
            $result = $this->transition($ticket, $actor, $to);
            $result->forceFill([
                'wait_reason' => trim($reason),
                'wait_until' => $until,
                'wait_owner_id' => $ownerId ?? $actor->id,
            ])->save();

            // Pause nur für deklarierte Gründe (SlaContract.pause_rules).
            $contract = $result->slaContract;
            $pausing = in_array($to->value, (array) ($contract->pause_rules ?? []), true);
            if ($pausing) {
                foreach ($this->openSlaTargets($result) as $target) {
                    SlaClockSegment::query()->create([
                        'organization_id' => $result->organization_id,
                        'service_ticket_id' => $result->id,
                        'target' => $target,
                        'paused_from' => Carbon::now(),
                        'reason' => $to->value,
                    ]);
                }
            }

            $result->audit('service_ticket.waiting', ['status' => $to->value, 'reason' => trim($reason), 'sla_paused' => $pausing]);

            return $result->refresh();
        });
    }

    /** Warten beenden: zurück nach in_progress, offene Uhr-Segmente schließen und Fristen um die Pausendauer verschieben. */
    public function resume(ServiceTicket $ticket, User $actor): ServiceTicket {
        return DB::transaction(function () use ($ticket, $actor): ServiceTicket {
            $result = $this->transition($ticket, $actor, ServiceTicketStatus::InProgress);

            $now = Carbon::now();
            $openSegments = SlaClockSegment::query()
                ->where('service_ticket_id', $result->id)
                ->whereNull('paused_to')
                ->get();
            foreach ($openSegments as $segment) {
                $segment->update(['paused_to' => $now]);
                $pausedMinutes = (int) round($segment->paused_from->diffInMinutes($now));
                $column = $segment->target === SlaClockSegment::TARGET_REACTION ? 'reaction_due_at' : 'resolution_due_at';
                $due = $result->getAttribute($column);
                if ($due !== null) {
                    $result->forceFill([$column => $due->copy()->addMinutes($pausedMinutes)])->save();
                }
            }

            $result->forceFill(['wait_reason' => null, 'wait_until' => null, 'wait_owner_id' => null])->save();
            $result->audit('service_ticket.resumed', ['paused_segments' => $openSegments->count()]);

            return $result->refresh();
        });
    }

    /**
     * Wiederöffnung (Feature 065, P1): nur aus done/accepted/closed mit Grund;
     * historische reaction/resolution-Zeitstempel bleiben unverändert (DoD).
     */
    public function reopen(ServiceTicket $ticket, User $actor, string $reason): ServiceTicket {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException((string) __('Wiederöffnung braucht einen Grund.'));
        }
        if (! in_array($ticket->status, [ServiceTicketStatus::Done, ServiceTicketStatus::Accepted, ServiceTicketStatus::Closed], true)) {
            throw ServiceTicketException::invalidStatusTransition($ticket->status->value, ServiceTicketStatus::InProgress->value);
        }

        $historic = [
            'acknowledged_at' => $ticket->acknowledged_at,
            'resolved_at' => $ticket->resolved_at,
        ];

        return DB::transaction(function () use ($ticket, $actor, $reason, $historic): ServiceTicket {
            $result = $this->transition($ticket, $actor, ServiceTicketStatus::InProgress);

            // Guard: Wiederöffnung darf die SLA-Historie nicht umschreiben.
            $result->forceFill([
                'acknowledged_at' => $historic['acknowledged_at'],
                'resolved_at' => $historic['resolved_at'],
                'started_at' => $result->started_at ?? Carbon::now(),
            ])->save();

            $result->audit('service_ticket.reopened', ['reason' => trim($reason)]);

            return $result->refresh();
        });
    }

    /**
     * Offene Frist-Ziele (nur nicht erreichte Fristen pausieren).
     *
     * @return array<int, string>
     */
    private function openSlaTargets(ServiceTicket $ticket): array {
        $targets = [];
        if ($ticket->reaction_due_at !== null && $ticket->acknowledged_at === null) {
            $targets[] = SlaClockSegment::TARGET_REACTION;
        }
        if ($ticket->resolution_due_at !== null && $ticket->resolved_at === null) {
            $targets[] = SlaClockSegment::TARGET_RESOLUTION;
        }

        return $targets;
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

    private function generateTicketNo(Organization $organization, Carbon $reportedAt): string {
        return $this->numberSequence->next($organization, NumberScope::ServiceTicket, $reportedAt);
    }

    /**
     * Löst einen im Payload explizit gesetzten SLA-Vertrag org-gescopt auf (nie cross-tenant).
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveExplicitContract(Organization $organization, array $payload): ?SlaContract {
        $contractId = isset($payload['sla_contract_id']) ? (int) $payload['sla_contract_id'] : null;
        if ($contractId === null) {
            return null;
        }

        return SlaContract::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereKey($contractId)
            ->first();
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
