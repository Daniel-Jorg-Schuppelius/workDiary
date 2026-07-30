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
use App\Models\{ExternalReference, IntegrationInboxItem, Task, TodoistConnection, TodoistProjectLink};
use App\Plugins\Todoist\Api\TodoistApiClient;
use App\Plugins\Todoist\Observers\TodoistTaskObserver;
use App\Plugins\Todoist\TodoistPlugin;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Aufgabenimport aus Todoist (Feature 055, MVP-113): je aktiver
 * Projektzuordnung werden Aufgaben über stabile Fremdreferenzen aufgelöst
 * (ein externer Task je Org = eine lokale Aufgabe). Der `base`-Snapshot im
 * `ExternalReference.payload` ist die 3-Wege-Konfliktbasis (MVP-114):
 * beidseitig geändert + ungleich → `conflict` in die Integrations-Inbox
 * statt Last-write-wins. Läufe sind idempotent; eine fehlerhafte Aufgabe
 * bricht den Projektlauf nicht ab.
 *
 * Feldadapter s. {@see self::mapFields()} (Plan 055); nicht-offensichtliche
 * Regeln (Reopen, Termine nur im Lesemodus, Eltern/Bearbeiter nur nach
 * Zuordnung) sind an der jeweiligen Codestelle kommentiert.
 */
class TodoistImportService {
    /** Felder des gemeinsamen base-Snapshots (Konfliktbasis). */
    public const SYNCED_FIELDS = ['title', 'description', 'status', 'priority', 'due_date', 'time_budget', 'assigned_to'];

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    public function syncLink(TodoistProjectLink $link, TodoistConnection $connection): array {
        if (! $link->importsFromTodoist() || $link->status !== TodoistProjectLink::STATUS_ACTIVE) {
            return TodoistSyncService::emptyCounters();
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
        $counters = TodoistSyncService::emptyCounters();
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
     * 3-Wege-Übernahme je Feld: base vs. lokal vs. remote. Nur remote
     * geänderte Felder fließen; beidseitig geändert + ungleich → Konflikt-
     * Inbox-Item mit diff_fields (MVP-114-Basis).
     *
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $mapped
     * @return 'updated'|'unchanged'|'conflicts'
     */
    private function applyThreeWay(TodoistProjectLink $link, ExternalReference $reference, Task $task, array $remote, array $mapped, ?int $parentTaskId): string {
        $payload = (array) ($reference->payload ?? []);
        $base = (array) ($payload['base'] ?? []);

        $changes = [];
        $conflicts = [];
        foreach (self::SYNCED_FIELDS as $field) {
            if (! array_key_exists($field, $mapped)) {
                continue; // Feld wird in diesem Lauf nicht geführt (z. B. Status ohne Abschnittszuordnung)
            }
            $remoteValue = $mapped[$field];
            $baseValue = $base[$field] ?? null;
            $localValue = $this->localValue($task, $field);

            $remoteChanged = $this->differs($remoteValue, $baseValue);
            $localChanged = $this->differs($localValue, $baseValue);

            if (! $remoteChanged) {
                continue; // remote unverändert → lokale Änderungen bleiben (Export in P4)
            }
            if ($localChanged && $this->differs($remoteValue, $localValue)) {
                $conflicts[$field] = ['local' => $localValue, 'remote' => $remoteValue];

                continue;
            }
            $changes[$field] = $remoteValue;
        }

        // Reopen-Regel: „wieder geöffnet" setzt nur zurück, wenn der lokale
        // done-Stand aus derselben Todoist-Synchronisation stammte.
        if (($changes['status'] ?? null) === TaskStatus::Open->value
            && $task->status === TaskStatus::Done
            && ($payload['done_origin'] ?? null) !== 'todoist') {
            unset($changes['status']);
        }

        if ($conflicts !== []) {
            $this->stageConflict($link, $reference, $task, $remote, $conflicts);
        }

        if ($changes === [] && $conflicts === []) {
            return 'unchanged';
        }

        if ($changes !== []) {
            if ($parentTaskId !== null && (int) $task->parent_task_id !== $parentTaskId) {
                $changes['parent_task_id'] = $parentTaskId;
            }
            // Übernommene Remote-Änderungen dürfen kein Export-Echo auslösen.
            TodoistTaskObserver::suppressed(fn () => $task->forceFill($changes)->save());
        }

        // base NUR für erfolgreich übernommene Felder fortschreiben —
        // Konfliktfelder behalten die alte Basis (sonst Phantom-Konflikte).
        $newBase = $this->baseFrom($task->refresh());
        foreach (array_keys($conflicts) as $field) {
            $newBase[$field] = $base[$field] ?? null;
        }
        $donOrigin = ($changes['status'] ?? null) === TaskStatus::Done->value ? 'todoist' : ($payload['done_origin'] ?? null);
        if (($changes['status'] ?? null) === TaskStatus::Open->value) {
            $donOrigin = null;
        }
        unset($payload['remote_deleted_at']); // Aufgabe ist remote (wieder) vorhanden
        $reference->forceFill([
            'payload' => [
                'remote' => $remote,
                'base' => $newBase,
                'section_id' => $remote['section_id'] ?? null,
                'done_origin' => $donOrigin,
            ] + $payload,
            'synced_at' => now(),
        ])->save();

        return $conflicts !== [] ? 'conflicts' : 'updated';
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

    /** @return array<string, mixed> */
    private function baseFrom(Task $task): array {
        $base = [];
        foreach (self::SYNCED_FIELDS as $field) {
            $base[$field] = $this->localValue($task, $field);
        }

        return $base;
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

    /**
     * Löschsemantik (MVP-114): remote verschwundene Aufgaben werden NIE
     * automatisch gelöscht — nur `remote_deleted_at`-Marker am
     * ExternalReference + sichtbarer Inbox-Fall (lokal archivieren /
     * entkoppeln / neu anlegen entscheidet der Mensch). Lokal erledigte
     * Aufgaben werden nicht markiert (erledigte Tasks fehlen in der
     * Aktiv-Liste der API, das wäre kein Löschsignal).
     *
     * @param  Collection<int, array<string, mixed>>  $remoteTasks
     * @param  array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}  $counters
     */
    private function flagRemoteDeletions(TodoistProjectLink $link, Collection $remoteTasks, array &$counters): void {
        $remoteIds = $remoteTasks->pluck('id')->map(fn ($id) => (string) $id)->all();

        $vanished = ExternalReference::query()
            ->forPlugin($link->organization_id, TodoistPlugin::ID, TodoistPlugin::EXT_TYPE_TASK)
            ->whereNotIn('external_id', $remoteIds)
            ->get();

        foreach ($vanished as $reference) {
            if ($this->markRemoteDeleted($link, $reference, explicit: false)) {
                $counters['inbox']++;
            }
        }
    }

    /**
     * Marker + Inbox-Fall für eine remote verschwundene/gelöschte Aufgabe.
     * `explicit` = ausdrückliches Löschsignal (Delta `is_deleted`) — dann wird
     * auch eine lokal erledigte Aufgabe markiert; ohne Signal (Aktivliste)
     * bleiben erledigte außen vor (Fehlen könnte bloße Erledigung sein).
     *
     * @return bool true, wenn ein NEUER Inbox-Fall entstanden ist
     */
    private function markRemoteDeleted(TodoistProjectLink $link, ExternalReference $reference, bool $explicit): bool {
        $task = $reference->referenceable;
        if (! $task instanceof Task) {
            return false; // beidseitig weg → nichts aufzulösen
        }
        // Nur Aufgaben dieser Zuordnung (Projekt bzw. globales Kanban).
        $belongsToLink = $link->target_kind === TodoistProjectLink::KIND_PROJECT
            ? (int) $task->project_id === (int) $link->project_id
            : (bool) $task->is_global;
        if (! $belongsToLink || (! $explicit && $task->status === TaskStatus::Done)) {
            return false;
        }

        $payload = (array) ($reference->payload ?? []);
        if (! isset($payload['remote_deleted_at'])) {
            $payload['remote_deleted_at'] = now()->toIso8601String();
            $reference->forceFill(['payload' => $payload])->save();
        }

        $item = IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organization_id,
            'plugin_id' => TodoistPlugin::ID,
            'dedupe_key' => 'task:' . $reference->external_id . ':remote_deleted',
        ], [
            'source' => 'todoist',
            'target_type' => $task->getMorphClass(),
            'external_type' => TodoistPlugin::EXT_TYPE_TASK,
            'external_id' => $reference->external_id,
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'local_snapshot' => $this->baseFrom($task),
            'remote_snapshot' => [],
            'display_title' => (string) $task->title,
            'display_subtitle' => $link->todoist_project_name ?? $link->todoist_project_id,
            'occurred_at' => now(),
        ]);

        return $item->wasRecentlyCreated;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return 'inbox'
     */
    private function stageInbox(TodoistProjectLink $link, array $remote, string $case): string {
        IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organization_id,
            'plugin_id' => TodoistPlugin::ID,
            'dedupe_key' => 'task:' . (string) ($remote['id'] ?? '') . ':' . $case,
        ], [
            'source' => 'todoist',
            'target_type' => (new Task())->getMorphClass(),
            'external_type' => TodoistPlugin::EXT_TYPE_TASK,
            'external_id' => (string) ($remote['id'] ?? ''),
            'case_type' => 'unmatched',
            'status' => 'open',
            'remote_snapshot' => $remote,
            'display_title' => (string) ($remote['content'] ?? '—'),
            'display_subtitle' => $link->todoist_project_name ?? $link->todoist_project_id,
            'occurred_at' => now(),
        ]);

        return 'inbox';
    }

    /**
     * Feldkonflikt (beidseitig geändert) → Inbox-Item `conflict` mit
     * diff_fields + beiden Snapshots. Die Inbox-UI bietet „lokal behalten"/
     * „remote übernehmen"; nichts wird still überschrieben.
     *
     * @param  array<string, mixed>  $remote
     * @param  array<string, array{local: mixed, remote: mixed}>  $conflicts
     */
    private function stageConflict(TodoistProjectLink $link, ExternalReference $reference, Task $task, array $remote, array $conflicts): void {
        IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organization_id,
            'plugin_id' => TodoistPlugin::ID,
            'dedupe_key' => 'task-conflict:' . $reference->external_id,
        ], [
            'source' => 'todoist',
            'target_type' => $task->getMorphClass(),
            'external_type' => TodoistPlugin::EXT_TYPE_TASK,
            'external_id' => $reference->external_id,
            'case_type' => 'conflict',
            'status' => 'open',
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'remote_snapshot' => $remote,
            'local_snapshot' => $this->baseFrom($task),
            'mapped_snapshot' => collect($conflicts)->map(fn (array $c) => $c['remote'])->all(),
            'diff_fields' => array_keys($conflicts),
            'display_title' => (string) $task->title,
            'display_subtitle' => $link->todoist_project_name ?? $link->todoist_project_id,
            'occurred_at' => now(),
        ]);
    }
}
