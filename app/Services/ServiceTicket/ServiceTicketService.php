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
use App\Enums\ServiceTicket\{ServiceTicketKind, ServiceTicketPriority, ServiceTicketSource, ServiceTicketStatus, TicketCloseCode};
use App\Exceptions\ServiceTicketException;
use App\Models\Asset\Asset;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket, ServiceTicketWatcher, SlaClockSegment, SlaContract};
use App\Services\Integration\LifecycleWebhookPublisher;
use App\Services\Numbering\NumberSequenceService;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServiceTicketService {
    use \App\Services\Concerns\ParsesMixedDate;

    // Herkunft des Vertrags im eingefrorenen Stand: nur Queue- und Organisationsvorgabe folgen einem Queue-Wechsel.
    private const string SLA_SOURCE_EXPLICIT = 'explicit';

    private const string SLA_SOURCE_ASSET = 'asset';

    private const string SLA_SOURCE_PROJECT = 'project';

    private const string SLA_SOURCE_CUSTOMER = 'customer';

    private const string SLA_SOURCE_QUEUE = 'queue';

    private const string SLA_SOURCE_ORGANIZATION = 'organization';

    public function __construct(
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
        $assetId = isset($payload['asset_id']) ? (int) $payload['asset_id'] : null;

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

        $kind = ServiceTicketKind::tryFrom((string) ($payload['kind'] ?? '')) ?? ServiceTicketKind::Incident;
        // Queue (Feature 065): ausdrücklich genannt, sonst die Standard-Queue — nimmt sie die Ticketart nicht an, die erste, die es tut.
        $queue = isset($payload['queue_id'])
            ? ServiceQueue::query()->where('organization_id', $organization->id)->whereKey((int) $payload['queue_id'])->first()
            : $this->queueFor($organization, $kind);
        // Extern geführte Queue (MVP-1592): lokal angelegt käme das Ticket nie ins führende System und bliebe gesperrt.
        if ($queue?->isExternallyLed() && ($payload['external_origin'] ?? null) === null) {
            throw \App\Exceptions\ExternallyLedQueueException::for($queue);
        }

        [$businessServiceId, $serviceOfferingId] = $this->resolveService(
            $organization,
            isset($payload['business_service_id']) ? (int) $payload['business_service_id'] : null,
            isset($payload['service_offering_id']) ? (int) $payload['service_offering_id'] : null,
        );

        $ticket = new ServiceTicket([
            'organization_id' => $organization->id,
            'queue_id' => $queue?->id,
            'business_service_id' => $businessServiceId,
            'service_offering_id' => $serviceOfferingId,
            'kind' => $kind->value,
            'confidentiality' => ($payload['confidentiality'] ?? null) === ServiceTicket::CONFIDENTIALITY_RESTRICTED
                ? ServiceTicket::CONFIDENTIALITY_RESTRICTED
                : ServiceTicket::CONFIDENTIALITY_NORMAL,
            'ticket_no' => (string) ($payload['ticket_no'] ?? $this->generateTicketNo($organization, $reportedAt)),
            'customer_id' => $customerId,
            'asset_id' => $assetId,
            'project_id' => $projectId,
            'diary_entry_id' => $diaryEntryId,
            // varchar(200): Titel aus Portal/Mail/Problem-Meldung („[Ref] Zusammenfassung“) liefen sonst über.
            'title' => StringHelper::truncate((string) ($payload['title'] ?? __('Neues Ticket')), 200, '…'),
            'description' => $payload['description'] ?? null,
            'status' => ServiceTicketStatus::Reported->value,
            'priority' => $priority->value,
            'source' => $source->value,
            'source_reference' => $payload['source_reference'] ?? null,
            'reported_by_user_id' => $actor?->id,
            'reported_at' => $reportedAt,
        ]);

        // Ausdrücklich genannter Vertrag gewinnt, sonst Asset → Projekt → Kunde → Queue → Organisationsvorgabe.
        $explicitContract = $this->resolveExplicitContract($organization, $payload);
        $contract = $explicitContract ?? $this->slaTimer->resolveContract($organization->id, $customerId, $projectId, $queue?->id, $assetId);
        if ($contract !== null) {
            $this->applySla($ticket, $contract, $queue, $reportedAt, $explicitContract !== null ? self::SLA_SOURCE_EXPLICIT : $this->slaSource($contract, $queue, $assetId));
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
        $routed = app(TicketRoutingService::class)->apply($ticket);

        $ticket->refresh();
        // Hat eine Regel die Queue gewechselt, ohne selbst ein SLA zu setzen, gilt die Vorgabe der neuen Queue.
        $slaSetByRule = array_filter($routed, static fn (array $entry): bool => isset($entry['actions']['set_sla'])) !== [];
        if ($explicitContract === null && ! $slaSetByRule && (int) $ticket->queue_id !== (int) $queue?->id) {
            $rerouted = $this->slaTimer->resolveContract($organization->id, $customerId, $projectId, $ticket->queue_id, $assetId);
            if ($rerouted !== null && (int) $rerouted->id !== (int) $ticket->sla_contract_id) {
                $ticket = $this->attachSla($ticket, $rerouted, $this->slaSource($rerouted, $ticket->queue()->first(), $assetId));
            }
        }

        // Wer ein vertrauliches Ticket anlegt, muss es danach noch öffnen können.
        if ($actor !== null && $ticket->confidentiality === ServiceTicket::CONFIDENTIALITY_RESTRICTED) {
            ServiceTicketWatcher::query()->firstOrCreate([
                'organization_id' => $ticket->organization_id,
                'service_ticket_id' => $ticket->id,
                'user_id' => $actor->id,
            ]);
        }

        // Lifecycle-Webhook (MVP-718): ticket.created nach Routing (Queue/Zuweisung final).
        app(LifecycleWebhookPublisher::class)->ticketCreated($ticket);

        return $ticket;
    }

    /**
     * Betroffener Service und Angebot (MVP-1257): nur Einträge der Organisation;
     * ein Angebot bringt seinen Service mit, ein fremdes Paar fällt weg.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function resolveService(Organization $organization, ?int $businessServiceId, ?int $serviceOfferingId): array {
        $offering = $serviceOfferingId !== null
            ? \App\Models\Sales\ServiceOffering::query()->where('organization_id', $organization->id)->whereKey($serviceOfferingId)->first(['id', 'business_service_id'])
            : null;
        if ($offering !== null) {
            if ($businessServiceId !== null && $businessServiceId !== (int) $offering->business_service_id) {
                $offering = null;
            } else {
                $businessServiceId = (int) $offering->business_service_id;
            }
        }
        $serviceExists = $businessServiceId !== null && \App\Models\ServiceTicket\BusinessService::query()
            ->where('organization_id', $organization->id)->whereKey($businessServiceId)->exists();

        return [$serviceExists ? $businessServiceId : null, $serviceExists ? $offering?->id : null];
    }

    /** Service am bestehenden Ticket setzen (Klassifizierung); protokolliert nur echte Änderungen. */
    public function setService(ServiceTicket $ticket, ?int $businessServiceId, ?int $serviceOfferingId): void {
        $organization = Organization::query()->findOrFail($ticket->organization_id);
        [$serviceId, $offeringId] = $this->resolveService($organization, $businessServiceId, $serviceOfferingId);
        $before = [$ticket->business_service_id, $ticket->service_offering_id];
        if ($before === [$serviceId, $offeringId]) {
            return;
        }

        $ticket->forceFill(['business_service_id' => $serviceId, 'service_offering_id' => $offeringId])->save();
        $ticket->audit('service_ticket.service_changed', [
            'from' => ['business_service_id' => $before[0], 'service_offering_id' => $before[1]],
            'to' => ['business_service_id' => $serviceId, 'service_offering_id' => $offeringId],
        ]);
    }

    /**
     * Vertraulichkeit (MVP-1548): wer ein Ticket vertraulich stellt, bleibt als
     * Beobachter berechtigt — sonst sperrte er sich selbst aus.
     */
    public function setConfidentiality(ServiceTicket $ticket, User $actor, string $confidentiality): void {
        $confidentiality = $confidentiality === ServiceTicket::CONFIDENTIALITY_RESTRICTED
            ? ServiceTicket::CONFIDENTIALITY_RESTRICTED
            : ServiceTicket::CONFIDENTIALITY_NORMAL;
        $before = (string) $ticket->confidentiality;
        if ($before === $confidentiality) {
            return;
        }

        $ticket->forceFill(['confidentiality' => $confidentiality])->save();
        if ($confidentiality === ServiceTicket::CONFIDENTIALITY_RESTRICTED) {
            ServiceTicketWatcher::query()->firstOrCreate([
                'organization_id' => $ticket->organization_id,
                'service_ticket_id' => $ticket->id,
                'user_id' => $actor->id,
            ]);
        }
        $ticket->audit('service_ticket.confidentiality_changed', ['from' => $before, 'to' => $confidentiality]);
    }

    public function transition(ServiceTicket $ticket, User $actor, ServiceTicketStatus $to): ServiceTicket {
        return $this->applyTransition($ticket, $actor, $to, requireAssignee: true);
    }

    /**
     * Abnahme der Lösung (done → accepted), auch durch den Kunden im Portal:
     * derselbe Weg wie intern, damit Zeitstempel, Audit und Folgeprozesse stimmen.
     */
    public function accept(ServiceTicket $ticket, User $actor): ServiceTicket {
        $result = $this->applyTransition($ticket, $actor, ServiceTicketStatus::Accepted, requireAssignee: false);
        if ($actor->isCustomer()) {
            $result->audit('service_ticket.accepted_by_customer', ['portal_user' => $actor->id]);
        }

        return $result;
    }

    private function applyTransition(ServiceTicket $ticket, User $actor, ServiceTicketStatus $to, bool $requireAssignee): ServiceTicket {
        // Der Status wird unter Zeilensperre gelesen: Zwei gleichzeitige Wechsel
        // (Bearbeiter und Portal, Zammad-Abgleich) dürfen nicht beide vom alten
        // Stand aus prüfen. Ungespeicherte Felder am Modell (Abschlusscode) bleiben.
        $state = DB::transaction(function () use ($ticket, $to, $requireAssignee): ?array {
            $locked = ServiceTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $from = $locked->status;
            if ($from === $to) {
                return null;
            }
            if (! $from->canTransitionTo($to)) {
                throw ServiceTicketException::invalidStatusTransition($from->value, $to->value);
            }
            if ($requireAssignee && $to === ServiceTicketStatus::InProgress && $locked->assigned_to_user_id === null) {
                throw ServiceTicketException::missingAssignee();
            }

            $wasAcknowledged = $locked->acknowledged_at !== null;
            $wasResolved = $locked->resolved_at !== null;
            $ticket->status = $to;
            $this->stampTransition($ticket, $to, Carbon::now());
            $ticket->save();

            return ['from' => $from, 'wasAcknowledged' => $wasAcknowledged, 'wasResolved' => $wasResolved];
        });
        if ($state === null) {
            return $ticket;
        }
        ['from' => $from, 'wasAcknowledged' => $wasAcknowledged, 'wasResolved' => $wasResolved] = $state;

        $ticket->audit('service_ticket.status_changed', [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        // Lifecycle-Webhook (MVP-718): ticket.closed an der Service-Schreibstelle.
        if ($to === ServiceTicketStatus::Closed) {
            app(LifecycleWebhookPublisher::class)->ticketClosed($ticket);
        }

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

    /**
     * Lösen oder Schließen (MVP-1546): Abschlusscode und Zusammenfassung stehen
     * vor dem Statuswechsel am Ticket, damit Webhook und Umfrage sie sehen.
     */
    public function conclude(ServiceTicket $ticket, User $actor, ServiceTicketStatus $to, TicketCloseCode $closeCode, ?string $summary = null): ServiceTicket {
        if (! in_array($to, [ServiceTicketStatus::Done, ServiceTicketStatus::Closed], true)) {
            throw new \InvalidArgumentException('Zielstatus ist kein Abschluss.');
        }
        $from = $ticket->status;
        if (! $from->canTransitionTo($to)) {
            throw ServiceTicketException::invalidStatusTransition($from->value, $to->value);
        }

        return DB::transaction(function () use ($ticket, $actor, $to, $closeCode, $summary): ServiceTicket {
            $ticket->close_code = $closeCode;
            if ($summary !== null && trim($summary) !== '') {
                $ticket->resolution_summary = trim($summary);
            }
            $result = $this->transition($ticket, $actor, $to);
            $result->audit('service_ticket.closed', ['close_code' => $closeCode->value, 'status' => $to->value]);

            return $result;
        });
    }

    /** Ohne $actor: Zuweisung durch eine Routing-Regel. */
    public function assign(ServiceTicket $ticket, ?User $actor, ?int $assigneeId): ServiceTicket {
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

        if (! $queue->supportsKind($ticket->kind)) {
            throw new \RuntimeException((string) __('helpdesk.queue.kindNotSupported', ['queue' => $queue->name, 'kind' => $ticket->kind->label()]));
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

        // Die Vorgabe der neuen Queue gilt nur, wenn der Vertrag bisher aus Queue oder Organisation kam
        // oder fehlte; Zusagen an Asset, Projekt und Kunde und ausdrücklich gesetzte Verträge bleiben.
        if ($this->slaFollowsQueue($ticket, $previous)) {
            $resolved = $this->slaTimer->resolveContract((int) $ticket->organization_id, $ticket->customer_id, $ticket->project_id, (int) $queue->id, $ticket->asset_id);
            if ($resolved !== null && (int) $resolved->id !== (int) $ticket->sla_contract_id) {
                return $this->attachSla($ticket, $resolved, $this->slaSource($resolved, $queue, $ticket->asset_id));
            }
        }

        return $ticket->refresh();
    }

    /** Ohne Herkunft im Snapshot (Tickets vor MVP-1548) entscheidet der Vertrag selbst. */
    private function slaFollowsQueue(ServiceTicket $ticket, ?int $previousQueueId): bool {
        if ($ticket->sla_contract_id === null) {
            return true;
        }
        $source = is_array($ticket->sla_snapshot) ? ($ticket->sla_snapshot['source'] ?? null) : null;
        if ($source !== null) {
            return in_array($source, [self::SLA_SOURCE_QUEUE, self::SLA_SOURCE_ORGANIZATION], true);
        }
        $contract = $ticket->slaContract;
        if ($contract === null || $contract->customer_id !== null || $contract->project_id !== null) {
            return false;
        }

        return $contract->is_default
            || ($previousQueueId !== null && ServiceQueue::query()->whereKey($previousQueueId)->where('default_sla_contract_id', $contract->id)->exists());
    }

    private function slaSource(SlaContract $contract, ?ServiceQueue $queue, ?int $assetId): string {
        return match (true) {
            $assetId !== null && Asset::query()->where('organization_id', $contract->organization_id)->whereKey($assetId)->where('sla_contract_id', $contract->id)->exists() => self::SLA_SOURCE_ASSET,
            $contract->project_id !== null => self::SLA_SOURCE_PROJECT,
            $contract->customer_id !== null => self::SLA_SOURCE_CUSTOMER,
            $queue !== null && (int) $queue->default_sla_contract_id === (int) $contract->id => self::SLA_SOURCE_QUEUE,
            default => self::SLA_SOURCE_ORGANIZATION,
        };
    }

    /** Ohne `$source` gilt der Vertrag als ausdrücklich gesetzt (Routing-Regel, Verwaltung) und bleibt bei einem Queue-Wechsel. */
    public function attachSla(ServiceTicket $ticket, SlaContract $contract, string $source = self::SLA_SOURCE_EXPLICIT): ServiceTicket {
        $this->applySla($ticket, $contract, $ticket->queue()->first(), $ticket->reported_at ?? Carbon::now(), $source);
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
     * Fristen und eingefrorener Vertragsstand; der Kalender der Queue ergänzt den Vertrag.
     * Spätere Vertragsänderungen deuten bestehende Tickets nie um (Feature 065, P3).
     */
    private function applySla(ServiceTicket $ticket, SlaContract $contract, ?ServiceQueue $queue, Carbon $reportedAt, string $source): void {
        $ticket->sla_contract_id = $contract->id;
        $deadlines = $this->slaTimer->computeDeadlines($contract, $ticket->priority, $reportedAt, $queue?->business_hours, $queue?->holiday_region);
        $ticket->reaction_due_at = $deadlines['reaction_due_at'];
        $ticket->resolution_due_at = $deadlines['resolution_due_at'];
        $ticket->sla_snapshot = [
            'contract_id' => $contract->id,
            'contract_name' => $contract->label,
            'priority_table' => $contract->priority_table,
            'business_hours' => $contract->business_hours ?: $queue?->business_hours,
            'holiday_region' => $queue?->holiday_region,
            'pause_rules' => $contract->pause_rules,
            'source' => $source,
            'frozen_at' => $reportedAt->toIso8601String(),
        ];
    }

    private function queueFor(Organization $organization, ServiceTicketKind $kind): ?ServiceQueue {
        $queues = ServiceQueue::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $default = $queues->firstWhere('is_default', true);
        // Extern geführte Queues nehmen nur Importe des führenden Systems an (MVP-1592).
        $own = $queues->reject(static fn (ServiceQueue $queue): bool => $queue->isExternallyLed());
        if ($default?->isExternallyLed()) {
            return $own->first(static fn (ServiceQueue $queue): bool => $queue->supportsKind($kind)) ?? $own->first();
        }
        if ($default === null || $default->supportsKind($kind)) {
            return $default;
        }

        return $own->first(static fn (ServiceQueue $queue): bool => $queue->supportsKind($kind)) ?? $default;
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
            // Eingefrorener Vertragsstand gilt vor dem heutigen Vertrag (Tickets vor dem Snapshot haben keinen).
            $pauseRules = is_array($result->sla_snapshot) && array_key_exists('pause_rules', $result->sla_snapshot)
                ? $result->sla_snapshot['pause_rules']
                : $result->slaContract?->pause_rules;
            $pausing = in_array($to->value, (array) ($pauseRules ?? []), true);
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

            $closed = $this->closePauseSegments($result);

            $result->forceFill(['wait_reason' => null, 'wait_until' => null, 'wait_owner_id' => null])->save();
            $result->audit('service_ticket.resumed', ['paused_segments' => $closed]);

            return $result->refresh();
        });
    }

    /**
     * Offene Uhr-Pausen schließen und die Fristen um die Pausendauer verschieben;
     * auch für das Fortsetzen durch eine Kundenantwort.
     */
    public function closePauseSegments(ServiceTicket $ticket): int {
        $now = Carbon::now();
        $openSegments = SlaClockSegment::query()
            ->where('service_ticket_id', $ticket->id)
            ->whereNull('paused_to')
            ->get();
        foreach ($openSegments as $segment) {
            $segment->update(['paused_to' => $now]);
            $pausedMinutes = (int) round($segment->paused_from->diffInMinutes($now));
            $column = $segment->target === SlaClockSegment::TARGET_REACTION ? 'reaction_due_at' : 'resolution_due_at';
            $due = $ticket->getAttribute($column);
            if ($due !== null) {
                $ticket->forceFill([$column => $due->copy()->addMinutes($pausedMinutes)])->save();
            }
        }

        return $openSegments->count();
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
            // Ohne Bearbeiter fällt das wieder geöffnete Ticket in die Queue zurück, statt die Wiederöffnung zu verweigern.
            $result = $this->applyTransition($ticket, $actor, ServiceTicketStatus::InProgress, requireAssignee: false);

            // Guard: Wiederöffnung darf die SLA-Historie nicht umschreiben.
            $result->forceFill([
                'acknowledged_at' => $historic['acknowledged_at'],
                'resolved_at' => $historic['resolved_at'],
                'started_at' => $result->started_at ?? Carbon::now(),
            ])->save();

            $result->audit('service_ticket.reopened', ['reason' => trim($reason)] + ($actor->isCustomer() ? ['portal_user' => $actor->id] : []));

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
            ->where('is_ola', false)
            ->whereKey($contractId)
            ->first();
    }

    private function parsePriority(string $value): ServiceTicketPriority {
        return ServiceTicketPriority::tryFrom($value) ?? ServiceTicketPriority::Normal;
    }

    private function parseSource(string $value): ServiceTicketSource {
        return ServiceTicketSource::tryFrom($value) ?? ServiceTicketSource::Manual;
    }

}
