<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Services;

use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{ExternalReference, Task, TodoistConnection, TodoistProjectLink};
use App\Plugins\Support\TaskSync\{AbstractTaskSyncService, TaskSyncLink};
use App\Plugins\Todoist\Api\TodoistApiClient;
use App\Plugins\Todoist\Observers\TodoistTaskObserver;
use App\Plugins\Todoist\TodoistPlugin;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Aufgabenimport aus Todoist (Feature 055, MVP-113): je aktiver
 * Projektzuordnung werden Aufgaben über stabile Fremdreferenzen aufgelöst
 * (ein externer Task je Org = eine lokale Aufgabe). 3-Wege-Kern, Reopen-,
 * Lösch- und Inbox-Semantik liefert {@see AbstractTaskSyncService}
 * (B8/Welle 3); hier bleiben Todoist-Spezifika: Subtask-Hierarchie
 * (parent_id), Abschnitts-/Bearbeiter-Zuordnung und der Feldadapter
 * {@see self::mapFields()} (Plan 055). Läufe sind idempotent; eine
 * fehlerhafte Aufgabe bricht den Projektlauf nicht ab.
 *
 * @extends AbstractTaskSyncService<TodoistProjectLink>
 */
class TodoistImportService extends AbstractTaskSyncService {
    /** Felder des gemeinsamen base-Snapshots (Konfliktbasis). */
    public const SYNCED_FIELDS = ['title', 'description', 'status', 'priority', 'due_date', 'time_budget', 'assigned_to'];

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    public function syncLink(TodoistProjectLink $link, TodoistConnection $connection): array {
        if (! $link->importsFromTodoist() || $link->status !== TodoistProjectLink::STATUS_ACTIVE) {
            return self::emptyCounters();
        }

        $api = new TodoistApiClient($connection);

        return $this->syncItems($link, collect($api->getTasks($link->todoist_project_id)), fullView: true);
    }

    /**
     * Verarbeitet eine Menge Remote-Aufgaben — die VOLLSTÄNDIGE Projektsicht
     * (`fullView`, REST-Aktivliste) oder ein Sync-/Webhook-DELTA (MVP-115).
     * Nur bei vollständiger Sicht werden verschwundene Aufgaben als „remote
     * gelöscht" markiert (ein Delta ist keine Abwesenheitsaussage); explizit
     * als `is_deleted` markierte Delta-Items werden direkt so behandelt,
     * `checked` fließt als done in den 3-Wege-Abgleich.
     *
     * @param  Collection<int, array<string, mixed>>  $remoteTasks
     * @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}
     */
    public function syncItems(TodoistProjectLink $link, Collection $remoteTasks, bool $fullView = false): array {
        $counters = self::emptyCounters();
        if (! $link->importsFromTodoist() || $link->status !== TodoistProjectLink::STATUS_ACTIVE) {
            return $counters;
        }

        // Explizit gelöschte Delta-Items separat behandeln — nie als Aufgabe verarbeiten.
        $isDeleted = fn (array $t): bool => (bool) ($t['is_deleted'] ?? false);
        $deleted = $remoteTasks->filter($isDeleted)->values();
        $present = $remoteTasks->reject($isDeleted)->values();

        $sectionMap = $link->sectionLinks()->pluck('task_status', 'todoist_section_id');
        $collaboratorMap = ExternalReference::query()
            ->forPlugin($link->organization_id, TodoistPlugin::ID, TodoistPlugin::EXT_TYPE_COLLABORATOR)
            ->pluck('referenceable_id', 'external_id');

        // Referenzen der Items UND ihrer Eltern laden — im Delta kann ein Kind
        // ohne sein (früher importiertes) Elternteil ankommen.
        $referenceIds = $present->pluck('id')->map(fn ($id) => (string) $id)
            ->merge($present->pluck('parent_id')->filter()->map(fn ($id) => (string) $id))
            ->unique()->values();

        /** @var Collection<string, ExternalReference> $references */
        $references = ExternalReference::query()
            ->forPlugin($link->organization_id, TodoistPlugin::ID, TodoistPlugin::EXT_TYPE_TASK)
            ->whereIn('external_id', $referenceIds)
            ->get()
            ->keyBy('external_id');

        // Eltern vor Kindern verarbeiten, damit parent_task_id auflösbar ist.
        $ordered = $present->sortBy(fn (array $t): int => empty($t['parent_id']) ? 0 : 1);

        foreach ($ordered as $remote) {
            try {
                $outcome = $this->syncOne($link, $remote, $references, $sectionMap, $collaboratorMap);
                $counters[$outcome]++;
            } catch (Throwable) {
                $counters['failed']++;
            }
        }

        foreach ($deleted as $remote) {
            $reference = $references->get((string) ($remote['id'] ?? ''))
                ?? ExternalReference::query()
                    ->forPlugin($link->organization_id, TodoistPlugin::ID, TodoistPlugin::EXT_TYPE_TASK)
                    ->forExternalId((string) ($remote['id'] ?? ''))
                    ->first();
            if ($reference !== null && $this->markRemoteDeleted($link, $reference, explicit: true)) {
                $counters['inbox']++;
            }
        }

        if ($fullView) {
            $this->flagRemoteDeletions($link, $present, $counters);
        }

        $link->forceFill(['last_run_at' => now(), 'last_run_counters' => $counters])->save();

        return $counters;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  Collection<string, ExternalReference>  $references
     * @param  \Illuminate\Support\Collection<string, string>  $sectionMap
     * @param  \Illuminate\Support\Collection<string, int|string>  $collaboratorMap
     * @return 'created'|'updated'|'unchanged'|'conflicts'|'inbox'
     */
    private function syncOne(TodoistProjectLink $link, array $remote, Collection $references, $sectionMap, $collaboratorMap): string {
        $externalId = (string) ($remote['id'] ?? '');
        if ($externalId === '') {
            throw new \RuntimeException('Todoist-Task ohne ID.');
        }
        $reference = $references->get($externalId);

        // Unteraufgabe: Elternauflösung nur innerhalb derselben Projektzuordnung.
        $parentTaskId = null;
        $parentExternal = isset($remote['parent_id']) ? (string) $remote['parent_id'] : '';
        if ($parentExternal !== '') {
            $parentRef = $references->get($parentExternal);
            // Referenz fehlt ODER der lokale Eltern-Task existiert nicht mehr.
            if ($parentRef === null || ! $parentRef->referenceable instanceof Task) {
                return $this->stageInbox($link, $remote, 'orphan_subtask');
            }
            $parentTaskId = (int) $parentRef->referenceable->getKey();
        }

        $mapped = $this->mapFields($remote, $sectionMap, $collaboratorMap);

        if ($reference === null) {
            // Anlage ist durch die bewusst aktivierte Zuordnung gedeckt (Preflight).
            // In suppressed() gekapselt, damit der created-Export-Trigger den
            // frisch importierten Task nicht sofort nach Todoist zurückspiegelt.
            $task = TodoistTaskObserver::suppressed(fn (): Task => Task::query()->create([
                'organization_id' => $link->organization_id,
                'project_id' => $link->target_kind === TodoistProjectLink::KIND_PROJECT ? $link->project_id : null,
                'is_global' => $link->target_kind === TodoistProjectLink::KIND_GLOBAL_KANBAN,
                'parent_task_id' => $parentTaskId,
                'title' => $mapped['title'],
                'description' => $mapped['description'],
                'status' => ($mapped['status'] ?? TaskStatus::Open->value),
                'priority' => $mapped['priority'],
                'due_date' => $mapped['due_date'],
                'time_budget' => $mapped['time_budget'],
                'assigned_to' => $mapped['assigned_to'],
            ]));

            $newReference = ExternalReference::query()->create([
                'organization_id' => $link->organization_id,
                'plugin_id' => TodoistPlugin::ID,
                'external_type' => TodoistPlugin::EXT_TYPE_TASK,
                'external_id' => $externalId,
                'referenceable_type' => $task->getMorphClass(),
                'referenceable_id' => $task->getKey(),
                'synced_at' => now(),
                'payload' => [
                    'remote' => $remote,
                    'base' => $this->baseFrom($task),
                    'section_id' => $remote['section_id'] ?? null,
                    'done_origin' => ($mapped['status'] ?? null) === TaskStatus::Done->value ? 'todoist' : null,
                ],
            ]);
            $references->put($externalId, $newReference);

            return 'created';
        }

        $task = $reference->referenceable;
        if (! $task instanceof Task) {
            // Lokal gelöscht, remote existiert weiter → sichtbarer Inbox-Fall,
            // nie stille Neuanlage oder Löschweitergabe.
            return $this->stageInbox($link, $remote, 'local_deleted');
        }

        return $this->applyThreeWay($link, $reference, $task, $remote, $mapped, $parentTaskId);
    }

    /**
     * Feldadapter Todoist → WorkDiary (Adaptertabelle 055).
     *
     * @param  array<string, mixed>  $remote
     * @param  \Illuminate\Support\Collection<string, string>  $sectionMap
     * @param  \Illuminate\Support\Collection<string, int|string>  $collaboratorMap
     * @return array<string, mixed>
     */
    private function mapFields(array $remote, $sectionMap, $collaboratorMap): array {
        $mapped = [
            'title' => (string) ($remote['content'] ?? '—'),
            'description' => isset($remote['description']) && $remote['description'] !== '' ? (string) $remote['description'] : null,
            'priority' => match ((int) ($remote['priority'] ?? 1)) {
                4 => TaskPriority::Urgent->value,  // API-Wert 4 = höchste (UI „p1")
                3 => TaskPriority::High->value,
                2 => TaskPriority::Medium->value,
                default => TaskPriority::Low->value,
            },
            'due_date' => isset($remote['due']['date']) ? (string) $remote['due']['date'] : null,
            'time_budget' => isset($remote['duration']['amount']) ? (int) $remote['duration']['amount'] : 0, // Spalte ist NOT NULL default 0
        ];

        // Bearbeiter nur nach expliziter Zuordnung — nie raten.
        $responsible = isset($remote['responsible_uid']) ? (string) $remote['responsible_uid'] : '';
        $mapped['assigned_to'] = $responsible !== '' && $collaboratorMap->has($responsible)
            ? (int) $collaboratorMap->get($responsible)
            : null;

        // Status: erledigt → done; sonst nur über explizite Abschnittszuordnung.
        if ((bool) ($remote['checked'] ?? false)) {
            $mapped['status'] = TaskStatus::Done->value;
        } else {
            $sectionId = isset($remote['section_id']) ? (string) $remote['section_id'] : '';
            if ($sectionId !== '' && $sectionMap->has($sectionId)) {
                $mapped['status'] = (string) $sectionMap->get($sectionId);
            } else {
                $mapped['status'] = TaskStatus::Open->value;
            }
        }

        return $mapped;
    }

    // ── Hooks des gemeinsamen Sync-Kerns ────────────────────────────────

    protected function pluginId(): string {
        return TodoistPlugin::ID;
    }

    protected function externalType(): string {
        return TodoistPlugin::EXT_TYPE_TASK;
    }

    protected function dedupePrefix(): string {
        return 'task';
    }

    protected function doneOriginMarker(): string {
        return 'todoist';
    }

    /** @return list<string> */
    protected function syncedFields(): array {
        return self::SYNCED_FIELDS;
    }

    /** @param TodoistProjectLink $link */
    protected function displaySubtitle(TaskSyncLink $link): string {
        return $link->todoist_project_name ?? $link->todoist_project_id;
    }

    /** @param array<string, mixed> $remote */
    protected function remoteDisplayTitle(array $remote): string {
        return (string) ($remote['content'] ?? '—');
    }

    protected function withoutExportEcho(callable $callback): mixed {
        return TodoistTaskObserver::suppressed($callback);
    }

    /**
     * @param  TodoistProjectLink  $link
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $newBase
     * @return array<string, mixed>
     */
    protected function updatedReferencePayload(TaskSyncLink $link, array $remote, array $newBase, mixed $doneOrigin): array {
        return [
            'remote' => $remote,
            'base' => $newBase,
            'section_id' => $remote['section_id'] ?? null,
            'done_origin' => $doneOrigin,
        ];
    }

    /** Nur Aufgaben dieser Zuordnung (Projekt bzw. globales Kanban). */
    /** @param TodoistProjectLink $link */
    protected function taskBelongsToLink(TaskSyncLink $link, Task $task): bool {
        return $link->target_kind === TodoistProjectLink::KIND_PROJECT
            ? (int) $task->project_id === (int) $link->project_id
            : (bool) $task->is_global;
    }
}
