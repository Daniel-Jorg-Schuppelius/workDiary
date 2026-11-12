<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadTicketImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Services;

use App\Enums\Integration\{DataDomain, IntegrationInboxStatus};
use App\Enums\ServiceTicket\{ServiceTicketStatus, TicketCloseCode};
use App\Enums\Task\TaskStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\{Organization, User};
use App\Models\Project\{Project, Task};
use App\Models\ServiceTicket\ServiceTicket;
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\Observers\ZammadTaskObserver;
use App\Plugins\Zammad\ZammadPlugin;
use App\Services\Integration\Attributes\WritesDataDomain;
use App\Services\Integration\Contracts\OwnershipConflictReplay;
use App\Services\Integration\DataOwnershipGuard;
use App\Services\Integration\Dto\OwnershipClaim;
use App\Services\Integration\Match\EntityMatcher;
use App\Services\Integration\Profiles\CustomerMatchProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Import von Zammad-Tickets als WorkDiary-Aufgaben (Feature 060, MVP-129). Das
 * Ticketsystem bleibt führend; hier entstehen nur Aufgaben für Zeiterfassung/
 * Abrechnung. **Idempotent** über {@see ExternalReference} (Plugin `zammad`,
 * Typ `ticket`, externe Ticket-ID): ein Replay legt keine Dubletten an. Queue
 * (Zammad-Gruppe) → Projekt über `queue_map`, sonst `default_project_id`, sonst
 * globale Aufgabe. Nie ein Projekt fremder Organisationen (Mandantengrenze).
 *
 * Neu angelegt werden nur offene Tickets (geschlossene holt der Import nicht
 * nach) und mit `is_limited_to_mapped_groups` nur zugeordnete Gruppen. Schließt
 * Zammad ein bereits verknüpftes Ticket, wird die Aufgabe erledigt.
 *
 * Service-Tickets bzw. Aufgaben schreibt der Import nur, wenn Zammad den
 * Bereich „Tickets“ bzw. „Aufgaben“ schreiben darf (MVP-1592); sonst entsteht
 * ein Führerschaftskonflikt, den {@see replayOwnershipConflict()} beim
 * Übernehmen einmal abspielt.
 */
#[WritesDataDomain(DataDomain::Tasks)]
#[WritesDataDomain(DataDomain::Tickets)]
class ZammadTicketImporter implements OwnershipConflictReplay {
    /** Höchstwert der Zammad-API je Seite. */
    public const int PAGE_SIZE = 100;

    /** Konfliktart: Service-Ticket aus Zammad anlegen. */
    public const string OWNERSHIP_CREATE = 'zammad.service_ticket';

    /** Konfliktart: Abschluss aus Zammad ans Service-Ticket übertragen. */
    public const string OWNERSHIP_CLOSE = 'zammad.service_ticket_close';

    /** Konfliktart: Aufgabe aus einem Zammad-Ticket anlegen. */
    public const string OWNERSHIP_TASK = 'zammad.task';

    /** Konfliktart: Abschluss aus Zammad an die Aufgabe übertragen. */
    public const string OWNERSHIP_TASK_CLOSE = 'zammad.task_close';

    /** Zammad-Status, die ein Ticket abschließen. */
    private const array CLOSED_STATES = ['closed', 'merged'];

    public function __construct(private readonly EntityMatcher $matcher = new EntityMatcher, private readonly CustomerMatchProfile $profile = new CustomerMatchProfile) {}

    /**
     * @return array{created: int, updated: int, skipped: int, inbox: int}
     */
    public function import(ZammadConnection $connection, ZammadGateway $gateway, ?User $actor = null): array {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $inbox = 0;

        foreach ($this->allTickets($gateway) as $ticket) {
            $externalId = (string) $ticket['id'];

            $reference = ExternalReference::query()
                ->forPlugin($connection->organization_id, ZammadPlugin::ID, ZammadPlugin::EXT_TYPE_TICKET)
                ->forExternalId($externalId)
                ->first();
            if ($reference instanceof ExternalReference) {
                $this->syncClosedState($reference, $ticket) ? $updated++ : $skipped++;
                continue;
            }

            // Seit dem Paging-Fix sähe der erste Lauf die ganze Historie: Geschlossenes holt er nicht nach (E19).
            if ($this->isClosed($ticket) || ! $connection->importsGroup($ticket['group_id'])) {
                $skipped++;
                continue;
            }

            // Zielmodus je Queue (Feature 065, P8): native ServiceTicket-Queue
            // → Import als ServiceTicket (Zammad bleibt führend, die Queue
            // trägt data_ownership=external); sonst Bestand (Task).
            if ($connection->ticket_target === 'service_ticket' && $connection->service_queue_id !== null) {
                $this->importServiceTicket($connection, $ticket, $actor) instanceof ServiceTicket ? $created++ : $inbox++;

                continue;
            }

            $task = $this->importTask($connection, $ticket, $actor);
            if (! $task instanceof Task) {
                $inbox++;

                continue;
            }
            $created++;

            // Kundenvorschlag (Rang 21): statt still im Default-Projekt zu landen,
            // einen Kunden vorschlagen bzw. bei eindeutigem Treffer automatisch
            // aufs Kundenprojekt umhängen.
            $inbox += $this->suggestCustomer($connection, $task, $ticket);
        }

        $connection->forceFill(['last_polled_at' => Carbon::now()])->save();

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'inbox' => $inbox];
    }

    /**
     * Aufgabe aus einem Zammad-Ticket, wenn Zammad den Aufgabenbereich
     * schreiben darf; sonst null und ein Führerschaftskonflikt im Eingang.
     *
     * @param  array<string, mixed>  $ticket
     */
    private function importTask(ZammadConnection $connection, array $ticket, ?User $actor): ?Task {
        $organization = Organization::query()->whereKey($connection->organization_id)->firstOrFail();
        $externalId = (string) $ticket['id'];
        if (! app(DataOwnershipGuard::class)->allows($organization, DataDomain::Tasks, ZammadPlugin::ID, new OwnershipClaim(
            kind: self::OWNERSHIP_TASK,
            key: $externalId,
            title: $this->taskTitle($ticket),
            target: Task::class,
            payload: ['connection_id' => $connection->id, 'ticket' => $ticket],
            externalId: $externalId,
            subtitle: (string) $connection->name,
            legacyKey: 'ownership-conflict:ticket:' . $externalId,
        ))) {
            return null;
        }

        $projectId = $this->resolveProject($connection, $ticket['group_id'] ?? null, $connection->queue_map ?? []);
        $task = Task::query()->create([
            'organization_id' => $connection->organization_id,
            'project_id' => $projectId,
            'is_global' => $projectId === null,
            'title' => $this->taskTitle($ticket),
            'status' => TaskStatus::Open->value,
            'billable' => true,
            'created_by' => $actor?->id,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $connection->organization_id,
            'plugin_id' => ZammadPlugin::ID,
            'external_type' => ZammadPlugin::EXT_TYPE_TICKET,
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->id,
            'external_id' => $externalId,
            'payload' => $ticket,
            'synced_at' => Carbon::now(),
        ]);

        return $task;
    }

    /**
     * Service-Ticket aus einem Zammad-Ticket, wenn Zammad den Ticketbereich
     * schreiben darf; sonst null und ein Führerschaftskonflikt im Eingang.
     *
     * @param  array<string, mixed>  $ticket
     */
    private function importServiceTicket(ZammadConnection $connection, array $ticket, ?User $actor): ?ServiceTicket {
        $organization = Organization::query()->whereKey($connection->organization_id)->firstOrFail();
        $externalId = (string) $ticket['id'];
        if (! app(DataOwnershipGuard::class)->allows($organization, DataDomain::Tickets, ZammadPlugin::ID, new OwnershipClaim(
            kind: self::OWNERSHIP_CREATE,
            key: $externalId,
            title: $this->taskTitle($ticket),
            target: ServiceTicket::class,
            payload: ['connection_id' => $connection->id, 'ticket' => $ticket],
            externalId: $externalId,
            subtitle: (string) $connection->name,
        ))) {
            return null;
        }

        $serviceTicket = app(\App\Services\ServiceTicket\ServiceTicketService::class)->create($organization, $actor, [
            'title' => $this->taskTitle($ticket),
            'description' => (string) $ticket['title'],
            'queue_id' => $connection->service_queue_id,
            'source' => \App\Enums\ServiceTicket\ServiceTicketSource::Api->value,
            'source_reference' => 'zammad:' . $externalId,
            // Die Queue führt Zammad; nur sein Import legt dort Tickets an.
            'external_origin' => ZammadPlugin::ID,
        ]);
        ExternalReference::query()->create([
            'organization_id' => $connection->organization_id,
            'plugin_id' => ZammadPlugin::ID,
            'external_type' => ZammadPlugin::EXT_TYPE_TICKET,
            'referenceable_type' => $serviceTicket->getMorphClass(),
            'referenceable_id' => $serviceTicket->id,
            'external_id' => $externalId,
            'payload' => $ticket,
            'synced_at' => Carbon::now(),
        ]);

        return $serviceTicket;
    }

    /** @return list<string> */
    public function ownershipKinds(): array {
        return [self::OWNERSHIP_CREATE, self::OWNERSHIP_CLOSE, self::OWNERSHIP_TASK, self::OWNERSHIP_TASK_CLOSE];
    }

    /** Übernehmen im Eingang: dieselben Schreibwege mit dem gespeicherten Zammad-Stand. */
    public function replayOwnershipConflict(Organization $organization, IntegrationInboxItem $item): ?Model {
        $payload = (array) (($item->remote_snapshot ?? [])['payload'] ?? []);
        $ticket = (array) ($payload['ticket'] ?? []);
        $connection = ZammadConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->find((int) ($payload['connection_id'] ?? 0));
        if (! $connection instanceof ZammadConnection || ! isset($ticket['id'])) {
            return null;
        }
        $reference = ExternalReference::query()
            ->forPlugin($organization, ZammadPlugin::ID, ZammadPlugin::EXT_TYPE_TICKET)
            ->forExternalId((string) $ticket['id'])
            ->first();

        $kind = $item->ownershipMarker()['kind'] ?? null;
        if ($kind === self::OWNERSHIP_CLOSE || $kind === self::OWNERSHIP_TASK_CLOSE) {
            return $reference instanceof ExternalReference && $this->syncClosedState($reference, $ticket) ? $reference->referenceable : null;
        }
        if ($reference instanceof ExternalReference) {
            return $reference->referenceable; // inzwischen angelegt
        }
        if ($kind === self::OWNERSHIP_TASK) {
            return $this->importTask($connection, $ticket, null);
        }
        if ($connection->ticket_target !== 'service_ticket' || $connection->service_queue_id === null) {
            throw new \RuntimeException((string) __('zammad::zammad.ownership.target_changed'));
        }

        return $this->importServiceTicket($connection, $ticket, null);
    }

    /** @param array<string, mixed> $ticket */
    private function isClosed(array $ticket): bool {
        return in_array($ticket['state'] ?? null, self::CLOSED_STATES, true);
    }

    /**
     * Schließt Zammad ein verknüpftes Ticket, wird die Aufgabe erledigt. Maßgeblich
     * ist der Übergang gegenüber dem gemerkten Ticketstand — sonst schlösse jeder
     * Lauf eine lokal wieder geöffnete Aufgabe erneut. Ein wiedereröffnetes Ticket
     * öffnet nichts (ohne Rückkanal kann die Aufgabe hier erledigt worden sein).
     *
     * @param  array<string, mixed>  $ticket
     */
    private function syncClosedState(ExternalReference $reference, array $ticket): bool {
        $closed = $this->isClosed($ticket);
        if ($closed === $this->isClosed((array) ($reference->payload ?? []))) {
            return false;
        }

        $task = $reference->referenceable;
        // Extern geführte Queue: in WorkDiary ist der Statuswechsel gesperrt, der Abschluss kommt von hier (MVP-1548).
        if ($closed && $task instanceof ServiceTicket && $task->isExternallyLed() && ! $task->status->isTerminal()) {
            $organization = Organization::query()->whereKey($reference->organization_id)->firstOrFail();
            // Gesperrt bleibt der gemerkte Stand alt: erst das Übernehmen überträgt den Abschluss.
            if (! app(DataOwnershipGuard::class)->allows($organization, DataDomain::Tickets, ZammadPlugin::ID, new OwnershipClaim(
                kind: self::OWNERSHIP_CLOSE,
                key: (string) $ticket['id'],
                title: $this->taskTitle($ticket),
                target: ServiceTicket::class,
                payload: ['connection_id' => (int) ZammadConnection::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->value('id'), 'ticket' => $ticket],
                externalId: (string) $ticket['id'],
                local: $task,
            ))) {
                return false;
            }
            $reference->forceFill(['payload' => $ticket, 'synced_at' => Carbon::now()])->save();
            $now = Carbon::now();
            $closeCode = $task->close_code ?? TicketCloseCode::Other;
            $task->forceFill([
                'status' => ServiceTicketStatus::Closed->value,
                'closed_at' => $now,
                'resolved_at' => $task->resolved_at ?? $now,
                'close_code' => $closeCode,
            ])->save();
            $task->audit('service_ticket.closed', ['close_code' => $closeCode->value, 'origin' => ZammadPlugin::ID]);

            return true;
        }
        if ($closed && $task instanceof Task && $task->status !== TaskStatus::Done) {
            $organization = Organization::query()->whereKey($reference->organization_id)->firstOrFail();
            // Gesperrt bleibt der gemerkte Stand alt: erst das Übernehmen überträgt den Abschluss.
            if (! app(DataOwnershipGuard::class)->allows($organization, DataDomain::Tasks, ZammadPlugin::ID, new OwnershipClaim(
                kind: self::OWNERSHIP_TASK_CLOSE,
                key: (string) $ticket['id'],
                title: $this->taskTitle($ticket),
                target: Task::class,
                payload: ['connection_id' => (int) ZammadConnection::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->value('id'), 'ticket' => $ticket],
                externalId: (string) $ticket['id'],
                local: $task,
            ))) {
                return false;
            }
        }
        $reference->forceFill(['payload' => $ticket, 'synced_at' => Carbon::now()])->save();
        if (! $closed || ! $task instanceof Task || $task->status === TaskStatus::Done) {
            return false;
        }

        // Kein Echo: der Abschluss kommt aus Zammad und geht nicht als Rückmeldung zurück.
        ZammadTaskObserver::suppressed(fn () => $task->forceFill(['status' => TaskStatus::Done->value])->save());

        return true;
    }

    /**
     * Alle Seiten der Ticketliste. Zammad sortiert nach ID aufsteigend — nur
     * die erste Seite hieße, dass ab dem 101. Ticket nie wieder ein neues ankommt.
     *
     * @return \Generator<int, array{id: int, number: string, title: string, group_id: int|null, state: string|null, customer_id: int|null, customer: string, organization: string}>
     */
    private function allTickets(ZammadGateway $gateway): \Generator {
        $seen = [];
        for ($page = 1; ; $page++) {
            $tickets = $gateway->listTickets(null, $page, self::PAGE_SIZE);
            $fresh = 0;
            foreach ($tickets as $ticket) {
                if (isset($seen[$ticket['id']])) {
                    continue;
                }
                $seen[$ticket['id']] = true;
                $fresh++;
                yield $ticket;
            }

            // Eine Seite ohne neue Tickets beendet den Lauf auch dann, wenn ein
            // Server `page` nicht auswertet und stets dieselbe Seite liefert.
            if (count($tickets) < self::PAGE_SIZE || $fresh === 0) {
                return;
            }
        }
    }

    /**
     * @param  array<int|string, int>  $queueMap
     */
    private function resolveProject(ZammadConnection $connection, ?int $groupId, array $queueMap): ?int {
        $candidate = $groupId !== null && isset($queueMap[$groupId]) ? (int) $queueMap[$groupId] : $connection->default_project_id;
        if ($candidate === null) {
            return null;
        }

        // Mandantengrenze: nur Projekte der eigenen Organisation.
        return Project::query()
            ->whereKey($candidate)
            ->where('organization_id', $connection->organization_id)
            ->exists() ? $candidate : null;
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function taskTitle(array $ticket): string {
        $rawTitle = (string) ($ticket['title'] ?? '');
        $number = (string) ($ticket['number'] ?? '');
        $title = $rawTitle !== '' ? $rawTitle : ('Ticket ' . $ticket['id']);

        return $number !== '' ? '#' . $number . ' ' . $title : $title;
    }

    /**
     * Moduswechsel (Feature 065, P8): Admin-Aktion mit Preflight — offene
     * Ownership-Konflikte blocken, der Wechsel wird als Migrationsprotokoll
     * auditiert und die Ziel-Queue als extern geführt markiert. Kein
     * stiller Mischbetrieb (DoD).
     */
    public function switchTicketTarget(ZammadConnection $connection, string $target, ?\App\Models\ServiceTicket\ServiceQueue $queue, User $actor): ZammadConnection {
        if (! in_array($target, ['task', 'service_ticket'], true)) {
            throw new \InvalidArgumentException("Unbekannter Zielmodus: {$target}");
        }
        if ($target === 'service_ticket' && $queue === null) {
            throw new \InvalidArgumentException((string) __('Der ServiceTicket-Modus braucht eine Ziel-Queue.'));
        }
        if ($queue !== null && (int) $queue->organization_id !== (int) $connection->organization_id) {
            throw new \RuntimeException((string) __('Queue gehört zu einer anderen Organisation.'));
        }

        // Preflight: offene Ownership-Konflikte müssen erst aufgelöst sein.
        $openConflicts = IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->where('plugin_id', ZammadPlugin::ID)
            ->whereIn('external_type', ['ticket_ownership_conflict', self::OWNERSHIP_CREATE, self::OWNERSHIP_TASK])
            ->where('status', IntegrationInboxStatus::Open)
            ->count();
        if ($openConflicts > 0) {
            throw new \RuntimeException((string) __(':count offene Zuordnungskonflikte — bitte zuerst auflösen.', ['count' => $openConflicts]));
        }

        $existingRefs = ExternalReference::query()
            ->forPlugin($connection->organization_id, ZammadPlugin::ID, ZammadPlugin::EXT_TYPE_TICKET)
            ->count();

        $previous = $connection->ticket_target;
        $previousQueueId = $connection->service_queue_id;
        $connection->forceFill([
            'ticket_target' => $target,
            'service_queue_id' => $queue?->id,
        ])->save();

        if ($queue !== null && $target === 'service_ticket') {
            // Zammad führt die Tickets dieser Queue (Datenführerschaft).
            $queue->forceFill(['data_ownership' => \App\Models\ServiceTicket\ServiceQueue::OWNERSHIP_EXTERNAL])->save();
        }
        // Die verlassene Queue führt WorkDiary wieder selbst, sobald keine Verbindung mehr in sie importiert.
        if ($previousQueueId !== null && (int) $previousQueueId !== (int) $connection->service_queue_id) {
            $stillTargeted = ZammadConnection::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $connection->organization_id)
                ->where('ticket_target', 'service_ticket')
                ->where('service_queue_id', $previousQueueId)
                ->exists();
            if (! $stillTargeted) {
                \App\Models\ServiceTicket\ServiceQueue::query()
                    ->where('organization_id', $connection->organization_id)
                    ->whereKey($previousQueueId)
                    ->first()
                    ?->forceFill(['data_ownership' => \App\Models\ServiceTicket\ServiceQueue::OWNERSHIP_NATIVE])
                    ->save();
            }
        }

        // Migrationsprotokoll (Audit): Bestandstickets bleiben unangetastet.
        $connection->audit('zammad.ticket_target_switched', [
            'from' => $previous,
            'to' => $target,
            'queue_id' => $queue?->id,
            'existing_references' => $existingRefs,
            'actor' => $actor->id,
        ]);

        return $connection->refresh();
    }

    /**
     * Kundenvorschlag beim Import (Rang 21): matcht Kunde über Ticket-E-Mail/
     * Organisation. Eindeutiger Treffer → Task aufs Kundenprojekt umhängen (0);
     * mehrdeutig/leer → Vorschlag in die Zuordnungs-Inbox (1).
     *
     * @param  array<string, mixed>  $ticket
     */
    private function suggestCustomer(ZammadConnection $connection, Task $task, array $ticket): int {
        $email = trim((string) ($ticket['customer'] ?? ''));
        $company = trim((string) ($ticket['organization'] ?? ''));
        if ($email === '' && $company === '') {
            return 0; // keine Kundendaten im Ticket
        }

        $organization = Organization::query()->find($connection->organization_id);
        if (! $organization instanceof Organization) {
            return 0;
        }

        $result = $this->matcher->match($organization, $this->profile, [
            'email' => $email !== '' ? $email : null,
            'company' => $company !== '' ? $company : null,
            'name' => $company !== '' ? $company : null,
        ]);

        // Eindeutiger Exakt-Treffer → automatisch aufs Kundenprojekt umhängen.
        $exact = $result->uniqueExact();
        if ($exact instanceof Customer) {
            $projectId = $this->customerProject($connection, $exact);
            if ($projectId !== null) {
                $task->forceFill(['project_id' => $projectId, 'is_global' => false])->save();
            }

            return 0;
        }

        $candidates = $result->candidates();
        $best = $candidates[0]['model'] ?? null;

        $item = IntegrationInboxItem::query()->firstOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'plugin_id' => ZammadPlugin::ID,
                'dedupe_key' => 'ticket-customer:' . $ticket['id'],
            ],
            [
                'source' => ZammadPlugin::ID,
                'target_type' => (new Customer)->getMorphClass(),
                'external_type' => 'ticket_customer',
                'external_id' => (string) $ticket['id'],
                'case_type' => $candidates !== [] ? IntegrationInboxItem::CASE_AMBIGUOUS : IntegrationInboxItem::CASE_UNMATCHED,
                'status' => IntegrationInboxStatus::Open,
                'referenceable_type' => $best instanceof Model ? $best->getMorphClass() : null,
                'referenceable_id' => $best instanceof Model ? $best->getKey() : null,
                'candidate_ids' => IntegrationInboxItem::candidatePayload($candidates),
                'remote_snapshot' => [
                    'ticket_id' => $ticket['id'],
                    'number' => (string) ($ticket['number'] ?? ''),
                    'title' => (string) ($ticket['title'] ?? ''),
                    'customer' => $email,
                    'organization' => $company,
                    'task_id' => $task->id,
                ],
                'display_title' => $this->taskTitle($ticket),
                'display_subtitle' => $email !== '' ? $email : $company,
                'occurred_at' => Carbon::now(),
            ],
        );

        return $item->wasRecentlyCreated ? 1 : 0;
    }

    /** Projekt des Kunden (Standardprojekt bevorzugt) in derselben Organisation. */
    private function customerProject(ZammadConnection $connection, Customer $customer): ?int {
        $project = Project::query()
            ->where('organization_id', $connection->organization_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('is_default')
            ->first();

        return $project?->id;
    }
}
