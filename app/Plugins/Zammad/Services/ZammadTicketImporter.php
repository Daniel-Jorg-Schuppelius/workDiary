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

use App\Models\{Customer, ExternalReference, IntegrationInboxItem, Organization, Project, Task, User, ZammadConnection};
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\ZammadPlugin;
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
 */
class ZammadTicketImporter {
    public function __construct(private readonly EntityMatcher $matcher = new EntityMatcher, private readonly CustomerMatchProfile $profile = new CustomerMatchProfile) {}

    /**
     * @return array{created: int, skipped: int, inbox: int}
     */
    public function import(ZammadConnection $connection, ZammadGateway $gateway, ?User $actor = null): array {
        $created = 0;
        $skipped = 0;
        $inbox = 0;
        $queueMap = $connection->queue_map ?? [];

        foreach ($gateway->listTickets() as $ticket) {
            $externalId = (string) $ticket['id'];

            $exists = ExternalReference::query()
                ->where('organization_id', $connection->organization_id)
                ->where('plugin_id', ZammadPlugin::ID)
                ->where('external_type', ZammadPlugin::EXT_TYPE_TICKET)
                ->where('external_id', $externalId)
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            // Zielmodus je Queue (Feature 065, P8): native ServiceTicket-Queue
            // → Import als ServiceTicket (Zammad bleibt führend, die Queue
            // trägt data_ownership=external); sonst Bestand (Task).
            if ($connection->ticket_target === 'service_ticket' && $connection->service_queue_id !== null) {
                $serviceTicket = app(\App\Services\ServiceTicket\ServiceTicketService::class)->create(
                    \App\Models\Organization::query()->whereKey($connection->organization_id)->firstOrFail(),
                    $actor,
                    [
                        'title' => $this->taskTitle($ticket),
                        'description' => (string) $ticket['title'],
                        'queue_id' => $connection->service_queue_id,
                        'source' => \App\Enums\ServiceTicket\ServiceTicketSource::Api->value,
                        'source_reference' => 'zammad:' . $externalId,
                    ],
                );
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
                $created++;

                continue;
            }

            // Datenführerschaft (Restpunkt 69): führt ein ANDERES Plugin den
            // Aufgabenbereich, landet das Ticket als Inbox-Konflikt statt
            // als Task-Write (native Führung erlaubt den Import wie bisher).
            $ownership = app(\App\Services\Integration\DataOwnershipResolver::class);
            $organization = \App\Models\Organization::query()->whereKey($connection->organization_id)->firstOrFail();
            if (! $ownership->mayWrite($organization, \App\Enums\Integration\DataDomain::Tasks, ZammadPlugin::ID)) {
                IntegrationInboxItem::query()->firstOrCreate(
                    [
                        'organization_id' => $connection->organization_id,
                        'plugin_id' => ZammadPlugin::ID,
                        'dedupe_key' => 'ownership-conflict:ticket:' . $ticket['id'],
                    ],
                    [
                        'source' => ZammadPlugin::ID,
                        'target_type' => (new Task)->getMorphClass(),
                        'external_type' => 'ticket_ownership_conflict',
                        'external_id' => (string) $ticket['id'],
                        'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
                        'status' => IntegrationInboxItem::STATUS_OPEN,
                        'remote_snapshot' => [
                            'ticket_id' => $ticket['id'],
                            'reason' => 'Aufgabenbereich wird von "' . $ownership->ownerFor($organization, \App\Enums\Integration\DataDomain::Tasks) . '" geführt.',
                        ],
                    ],
                );
                $inbox++;

                continue;
            }

            $projectId = $this->resolveProject($connection, $ticket['group_id'], $queueMap);
            $task = Task::query()->create([
                'organization_id' => $connection->organization_id,
                'project_id' => $projectId,
                'is_global' => $projectId === null,
                'title' => $this->taskTitle($ticket),
                'status' => in_array($ticket['state'], ['closed', 'merged'], true) ? 'done' : 'open',
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
            $created++;

            // Kundenvorschlag (Rang 21): statt still im Default-Projekt zu landen,
            // einen Kunden vorschlagen bzw. bei eindeutigem Treffer automatisch
            // aufs Kundenprojekt umhängen.
            $inbox += $this->suggestCustomer($connection, $task, $ticket);
        }

        $connection->forceFill(['last_polled_at' => Carbon::now()])->save();

        return ['created' => $created, 'skipped' => $skipped, 'inbox' => $inbox];
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
    public function switchTicketTarget(ZammadConnection $connection, string $target, ?\App\Models\ServiceQueue $queue, User $actor): ZammadConnection {
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
            ->where('external_type', 'ticket_ownership_conflict')
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->count();
        if ($openConflicts > 0) {
            throw new \RuntimeException((string) __(':count offene Zuordnungskonflikte — bitte zuerst auflösen.', ['count' => $openConflicts]));
        }

        $existingRefs = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $connection->organization_id)
            ->where('plugin_id', ZammadPlugin::ID)
            ->where('external_type', ZammadPlugin::EXT_TYPE_TICKET)
            ->count();

        $previous = $connection->ticket_target;
        $connection->forceFill([
            'ticket_target' => $target,
            'service_queue_id' => $queue?->id,
        ])->save();

        if ($queue !== null && $target === 'service_ticket') {
            // Zammad führt die Tickets dieser Queue (Datenführerschaft).
            $queue->forceFill(['data_ownership' => 'external'])->save();
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
                'status' => IntegrationInboxItem::STATUS_OPEN,
                'referenceable_type' => $best instanceof Model ? $best->getMorphClass() : null,
                'referenceable_id' => $best instanceof Model ? $best->getKey() : null,
                'candidate_ids' => $this->candidatePayload($candidates),
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

    /**
     * @param  list<array{model: Model, confidence: string, reasons: list<string>}>  $candidates
     * @return list<array<string, mixed>>
     */
    private function candidatePayload(array $candidates): array {
        $out = [];
        foreach ($candidates as $candidate) {
            $model = $candidate['model'];
            $out[] = [
                'id' => $model->getKey(),
                'sqid' => $model->getRouteKey(),
                'label' => (string) ($model->getAttribute('name') ?? $model->getAttribute('company') ?? ''),
                'confidence' => $candidate['confidence'],
                'reasons' => $candidate['reasons'],
            ];
        }

        return $out;
    }
}
