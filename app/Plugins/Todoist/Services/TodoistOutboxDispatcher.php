<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Services;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry, Task, TodoistConnection, TodoistProjectLink};
use App\Plugins\Todoist\Api\TodoistApiClient;
use App\Plugins\Todoist\TodoistPlugin;
use RuntimeException;

/**
 * Exportiert WorkDiary-geführte Aufgabenänderungen nach Todoist
 * (Feature 055, MVP-114) — Gegenstück zum Import-Feldadapter: title→content,
 * priority urgent/high/medium/low→4/3/2/1, due_date→due_date,
 * time_budget→duration (Minuten), assigned_to→responsible_uid (nur über
 * explizite Kollaborator-Zuordnung), Done-Grenze→close/reopen. Der Dispatcher
 * liest den AKTUELLEN lokalen Stand und überträgt nur Felder, die von der
 * gemeinsamen Konfliktbasis (`base` im ExternalReference-Payload) abweichen;
 * nach Erfolg wird `base` genau für die übertragenen Felder fortgeschrieben
 * (sonst Phantom-Konflikte beim nächsten Import). Löschungen werden nie
 * weitergegeben (kein `data:delete`-Scope).
 */
class TodoistOutboxDispatcher implements IntegrationOutboxDispatcher {
    public const OP_TASK_UPDATE = 'task.update';

    public const OP_TASK_CREATE = 'task.create';

    public function pluginId(): string {
        return TodoistPlugin::ID;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        return match ($entry->operation) {
            self::OP_TASK_CREATE => $this->dispatchCreate($entry),
            self::OP_TASK_UPDATE => $this->dispatchUpdate($entry),
            default => throw new RuntimeException('Unbekannte Todoist-Outbox-Operation: ' . $entry->operation),
        };
    }

    /**
     * Legt eine lokal entstandene Aufgabe in Todoist an und verankert sie über
     * eine neue {@see ExternalReference} inkl. initialem `base`-Snapshot
     * (Konfliktbasis). Doppelanlage-Schutz: eine bereits verknüpfte Aufgabe wird
     * übersprungen (idempotent zusammen mit dem Outbox-Schlüssel).
     */
    private function dispatchCreate(IntegrationOutboxEntry $entry): bool {
        $payload = $entry->payload;
        $task = Task::query()->withoutGlobalScopes()->find((int) ($payload['task_id'] ?? $entry->subject_id ?? 0));
        if (! $task instanceof Task) {
            return true; // lokal gelöscht → nichts anzulegen
        }

        $alreadyLinked = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('plugin_id', TodoistPlugin::ID)
            ->where('external_type', TodoistPlugin::EXT_TYPE_TASK)
            ->where('referenceable_type', $task->getMorphClass())
            ->where('referenceable_id', $task->getKey())
            ->exists();
        if ($alreadyLinked) {
            return true; // schon exportiert/importiert → keine Doppelanlage
        }

        $link = $this->resolveLink($task);
        if ($link === null || ! $link->exportsToTodoist() || $link->status !== TodoistProjectLink::STATUS_ACTIVE) {
            return true; // Exportrichtung nicht (mehr) aktiv
        }

        $connection = TodoistConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->first();
        if ($connection === null || ! $connection->isActive()) {
            throw new RuntimeException('Todoist-Verbindung inaktiv.'); // Queue wiederholt
        }

        // Idempotenz gegen Queue-Retry nach Teil-Erfolg (createTask ok,
        // Crash vor ExternalReference::create): stabiler X-Request-Id je
        // Outbox-Eintrag — Todoist dedupliziert den zweiten Create.
        $created = (new TodoistApiClient($connection))->createTask(
            $this->buildCreatePayload($task, $link),
            substr(hash('sha256', 'todoist-task-create-' . $entry->id), 0, 36),
        );
        $externalId = isset($created['id']) && is_scalar($created['id']) ? (string) $created['id'] : '';
        if ($externalId === '') {
            throw new RuntimeException('Todoist createTask lieferte keine id.');
        }

        $base = [];
        foreach (TodoistImportService::SYNCED_FIELDS as $field) {
            $base[$field] = $this->localValue($task, $field);
        }

        ExternalReference::query()->create([
            'organization_id' => $entry->organization_id,
            'plugin_id' => TodoistPlugin::ID,
            'external_type' => TodoistPlugin::EXT_TYPE_TASK,
            'external_id' => $externalId,
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'synced_at' => now(),
            'payload' => ['base' => $base],
        ]);

        return true;
    }

    /**
     * Baut das Todoist-`create`-Payload aus dem lokalen Aufgabenstand (gleiche
     * Feldabbildung wie der Update-Export; Zielprojekt aus dem Link).
     *
     * @return array<string, mixed>
     */
    private function buildCreatePayload(Task $task, TodoistProjectLink $link): array {
        $payload = [
            'content' => (string) $task->title,
            'project_id' => $link->todoist_project_id,
            'priority' => match ($task->priority) {
                TaskPriority::Urgent => 4,
                TaskPriority::High => 3,
                TaskPriority::Medium => 2,
                default => 1,
            },
        ];

        if ($task->description !== null && $task->description !== '') {
            $payload['description'] = (string) $task->description;
        }
        if ($task->due_date !== null) {
            $payload['due_date'] = $task->due_date->format('Y-m-d');
        }
        if ((int) $task->time_budget > 0) {
            $payload['duration'] = (int) $task->time_budget;
            $payload['duration_unit'] = 'minute';
        }
        $responsible = $this->collaboratorExternalId($task);
        if ($responsible !== null) {
            $payload['responsible_uid'] = $responsible;
        }

        return $payload;
    }

    private function dispatchUpdate(IntegrationOutboxEntry $entry): bool {
        $payload = $entry->payload;
        // task_id aus dem Payload (Observer) oder dem Morph-Subject (z. B.
        // „lokal behalten"-Entscheidung aus der Inbox, MVP-116).
        $task = Task::query()->withoutGlobalScopes()->find((int) ($payload['task_id'] ?? $entry->subject_id ?? 0));
        if (! $task instanceof Task) {
            return true; // lokal gelöscht → keine Löschweitergabe, nichts zu übertragen
        }

        $reference = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('plugin_id', TodoistPlugin::ID)
            ->where('external_type', TodoistPlugin::EXT_TYPE_TASK)
            ->where('external_id', (string) ($payload['external_id'] ?? ''))
            ->first();
        if ($reference === null) {
            return true; // entkoppelt → nichts zu übertragen
        }

        $link = $this->resolveLink($task);
        if ($link === null || ! $link->exportsToTodoist() || $link->status !== TodoistProjectLink::STATUS_ACTIVE) {
            return true; // Exportrichtung nicht (mehr) aktiv → bewusst kein Transfer
        }

        $connection = TodoistConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->first();
        if ($connection === null || ! $connection->isActive()) {
            throw new RuntimeException('Todoist-Verbindung inaktiv.'); // Queue wiederholt
        }

        $refPayload = (array) ($reference->payload ?? []);
        $base = (array) ($refPayload['base'] ?? []);

        // Offener Feldkonflikt (Import hat beidseitige Änderung erkannt):
        // die betroffenen Felder dürfen NICHT exportiert werden, sonst käme
        // Last-write-wins durch die Hintertür — Auflösung nur über die Inbox.
        $conflicted = (array) (IntegrationInboxItem::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('plugin_id', TodoistPlugin::ID)
            ->where('dedupe_key', 'task-conflict:' . $reference->external_id)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->value('diff_fields') ?? []);

        // Nur Felder übertragen, die wirklich von der Konfliktbasis abweichen —
        // hat der Import die Basis inzwischen fortgeschrieben, ist nichts zu tun.
        $diff = [];
        foreach (TodoistImportService::SYNCED_FIELDS as $field) {
            if (in_array($field, $conflicted, true)) {
                continue;
            }
            if ($this->differs($this->localValue($task, $field), $base[$field] ?? null)) {
                $diff[] = $field;
            }
        }
        if ($diff === []) {
            return true;
        }

        $api = new TodoistApiClient($connection);
        $externalId = (string) $reference->external_id;
        $update = [];
        $exported = [];

        foreach ($diff as $field) {
            switch ($field) {
                case 'title':
                    $update['content'] = (string) $task->title;
                    $exported[] = $field;
                    break;
                case 'description':
                    $update['description'] = (string) ($task->description ?? '');
                    $exported[] = $field;
                    break;
                case 'priority':
                    $update['priority'] = match ($task->priority) {
                        TaskPriority::Urgent => 4, // API-Wert 4 = höchste (UI „p1")
                        TaskPriority::High => 3,
                        TaskPriority::Medium => 2,
                        default => 1,
                    };
                    $exported[] = $field;
                    break;
                case 'due_date':
                    if ($task->due_date !== null) {
                        $update['due_date'] = $task->due_date->format('Y-m-d');
                    } else {
                        $update['due_string'] = 'no date'; // API-Idiom zum Entfernen des Termins
                    }
                    $exported[] = $field;
                    break;
                case 'time_budget':
                    if ((int) $task->time_budget > 0) {
                        $update['duration'] = (int) $task->time_budget;
                        $update['duration_unit'] = 'minute';
                        $exported[] = $field;
                    }
                    // 0 = kein verlässliches „Dauer entfernen" — Feld bleibt lokal führend
                    break;
                case 'assigned_to':
                    $responsible = $this->collaboratorExternalId($task);
                    if ($task->assigned_to === null || $responsible !== null) {
                        $update['responsible_uid'] = $responsible;
                        $exported[] = $field;
                    }
                    // ungemappter Bearbeiter → nie raten, Feld nicht übertragen
                    break;
            }
        }

        if ($update !== []) {
            $api->updateTask($externalId, $update);
        }

        // Statuswechsel: (1) Abschnitts-Move, sofern der Status einer Todoist-
        // Sektion zugeordnet ist (Gegenstück zur Import-Section-Map, kein
        // Ping-Pong dank base-Fortschreibung), (2) Done-Grenze über close/reopen.
        if (in_array('status', $diff, true)) {
            $statusExported = false;

            $sectionId = $this->sectionForStatus($link, (string) $task->status->value);
            if ($sectionId !== null) {
                $api->moveTask($externalId, $sectionId);
                $statusExported = true;
            }

            $localDone = $task->status === TaskStatus::Done;
            $baseDone = ($base['status'] ?? null) === TaskStatus::Done->value;
            if ($localDone && ! $baseDone) {
                $api->closeTask($externalId);
                $statusExported = true;
            } elseif (! $localDone && $baseDone) {
                $api->reopenTask($externalId);
                $statusExported = true;
            }

            if ($statusExported) {
                $exported[] = 'status';
            }
        }

        if ($exported !== []) {
            foreach ($exported as $field) {
                $base[$field] = $this->localValue($task, $field);
            }
            $refPayload['base'] = $base;
            if (in_array('status', $exported, true)) {
                // done wurde lokal gesetzt → lokal geführt: Todoist-Reopen setzt
                // nicht zurück (done_origin bleibt leer, Regel aus MVP-113).
                $refPayload['done_origin'] = null;
            }
            $reference->forceFill(['payload' => $refPayload, 'synced_at' => now()])->save();
        }

        return true;
    }

    /** Todoist-Abschnitt, der dem WorkDiary-Status zugeordnet ist (sonst null). */
    private function sectionForStatus(TodoistProjectLink $link, string $status): ?string {
        $sectionId = $link->sectionLinks()->where('task_status', $status)->value('todoist_section_id');

        return is_string($sectionId) && $sectionId !== '' ? $sectionId : null;
    }

    /** Zuordnung der Aufgabe: Projekt-Link bzw. globales Kanban. */
    private function resolveLink(Task $task): ?TodoistProjectLink {
        return TodoistProjectLink::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->when($task->project_id !== null, fn ($q) => $q
                ->where('target_kind', TodoistProjectLink::KIND_PROJECT)
                ->where('project_id', $task->project_id))
            ->when($task->project_id === null, fn ($q) => $q
                ->where('target_kind', TodoistProjectLink::KIND_GLOBAL_KANBAN))
            ->first();
    }

    /** Todoist-Kollaborator-ID des Bearbeiters — nur über explizite Zuordnung. */
    private function collaboratorExternalId(Task $task): ?string {
        if ($task->assigned_to === null) {
            return null;
        }

        $externalId = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->where('plugin_id', TodoistPlugin::ID)
            ->where('external_type', TodoistPlugin::EXT_TYPE_COLLABORATOR)
            ->where('referenceable_id', $task->assigned_to)
            ->value('external_id');

        return $externalId !== null ? (string) $externalId : null;
    }

    private function localValue(Task $task, string $field): mixed {
        $value = $task->getAttribute($field);
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    private function differs(mixed $a, mixed $b): bool {
        return ($a === null ? null : (string) $a) !== ($b === null ? null : (string) $b);
    }
}
