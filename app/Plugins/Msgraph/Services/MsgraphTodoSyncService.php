<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTodoSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{ExternalReference, IntegrationInboxItem, MsgraphTaskConnection, MsgraphTaskListLink, Organization, Task};
use App\Plugins\Msgraph\Api\MsgraphTodoClient;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Msgraph\Observers\MsgraphTodoTaskObserver;
use App\Services\CloudIntake\StaleCheckpointException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Microsoft-To-Do-Sync (Feature 102, Schnitt E) nach dem Todoist-Muster
 * (Feature 055, {@see \App\Plugins\Todoist\Services\TodoistImportService}):
 * je aktiver Listen-Zuordnung wird über stabile Fremdreferenzen abgeglichen
 * (ein To-Do-Task je Org = eine lokale Aufgabe). Der `base`-Snapshot im
 * `ExternalReference.payload` ist die 3-Wege-Konfliktbasis — beidseitig
 * geändert + ungleich ⇒ `conflict` in die Integrations-Inbox, NIE
 * Last-write-wins. Remote verschwundene Aufgaben werden nur markiert
 * (Inbox-Fall), nie gelöscht.
 *
 * Unterschiede zu Todoist (bewusst, To-Do-API): keine Abschnitte, keine
 * Bearbeiter-Zuordnung, keine Unteraufgaben (Checklist-Items sind kein
 * Task-Baum). Neue Exporte tragen eine `linkedResource` zurück zur
 * WorkDiary-Aufgabe. Der Import läuft über die Delta-Query (Checkpoint
 * `delta_link` je Listen-Link); der Export zusätzlich live über den
 * {@see \App\Plugins\Msgraph\Observers\MsgraphTodoTaskObserver} →
 * {@see MsgraphOutboxDispatcher} ({@see exportTask()}) — der Sync-Lauf
 * bleibt die heilende Quelle.
 */
class MsgraphTodoSyncService {
    /** Felder des gemeinsamen base-Snapshots (Konfliktbasis). */
    public const SYNCED_FIELDS = ['title', 'description', 'status', 'priority', 'due_date'];

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    public static function emptyCounters(): array {
        return ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0, 'inbox' => 0, 'failed' => 0];
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    public function syncOrganization(Organization $organization): array {
        $counters = self::emptyCounters();

        $connection = MsgraphTaskConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->first();
        if (! $connection instanceof MsgraphTaskConnection || ! $connection->isActive()) {
            return $counters;
        }

        $links = MsgraphTaskListLink::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('status', MsgraphTaskListLink::STATUS_ACTIVE)
            ->get();

        foreach ($links as $link) {
            try {
                $result = $this->syncLink($link, $connection);
                foreach ($counters as $key => $value) {
                    $counters[$key] = $value + $result[$key];
                }
                $connection->recordConnectionSuccess();
            } catch (Throwable $e) {
                $counters['failed']++;
                $connection->recordConnectionFailure(class_basename($e));
            }
        }

        $connection->forceFill(['last_sync_at' => now()])->save();

        return $counters;
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    public function syncLink(MsgraphTaskListLink $link, MsgraphTaskConnection $connection): array {
        $counters = self::emptyCounters();
        $client = new MsgraphTodoClient($connection);

        if ($link->importsFromTodo()) {
            $result = $this->importFromRemote($link, $client);
            foreach ($counters as $key => $value) {
                $counters[$key] = $value + $result[$key];
            }
        }

        if ($link->exportsToTodo()) {
            $result = $this->export($link, $client);
            foreach ($counters as $key => $value) {
                $counters[$key] = $value + $result[$key];
            }
        }

        $link->forceFill(['last_run_at' => now(), 'last_run_counters' => $counters])->save();

        return $counters;
    }

    // ── Import: To Do → WorkDiary (3-Wege-Kern, Todoist-Muster) ─────────

    /**
     * Import über die Delta-Query (Folgeausbau): ohne Checkpoint liefert die
     * Delta-Kette die VOLLSTÄNDIGE Sicht (inkl. Lösch-Voll-Abgleich), mit
     * Checkpoint nur Änderungen — `@removed`-Einträge werden explizit als
     * remote-gelöscht behandelt, `flagRemoteDeletions()` entfällt dort
     * (Teilsicht!). Abgelaufener Checkpoint (410) ⇒ Neuaufbau ab voller Sicht.
     *
     * @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}
     */
    private function importFromRemote(MsgraphTaskListLink $link, MsgraphTodoClient $client): array {
        $checkpoint = trim((string) $link->delta_link);
        $incremental = $checkpoint !== '';

        try {
            [$items, $newCheckpoint] = $this->collectDelta($client, $link->todo_list_id, $incremental ? $checkpoint : null);
        } catch (StaleCheckpointException) {
            $link->forceFill(['delta_link' => null])->save();
            [$items, $newCheckpoint] = $this->collectDelta($client, $link->todo_list_id, null);
            $incremental = false;
        }

        $remoteTasks = collect($items);
        $result = $incremental
            ? $this->importDelta($link, $remoteTasks)
            // Erstlauf: @removed defensiv ausfiltern (volle Sicht kennt nur Bestand).
            : $this->import($link, $remoteTasks->filter(fn (array $t): bool => ! isset($t['@removed']))->values());

        if ($newCheckpoint !== '') {
            $link->forceFill(['delta_link' => $newCheckpoint])->save();
        }

        return $result;
    }

    /**
     * Löst die Delta-Kette vollständig auf (nextLink-Seiten bis zum deltaLink).
     *
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private function collectDelta(MsgraphTodoClient $client, string $listId, ?string $checkpoint): array {
        $items = [];
        do {
            $page = $client->tasksDelta($listId, $checkpoint);
            foreach ($page['items'] as $item) {
                $items[] = $item;
            }
            $checkpoint = $page['checkpoint'];
        } while ($page['hasMore']);

        return [$items, (string) $checkpoint];
    }

    /**
     * Inkrementeller Import einer Delta-Änderungsmenge (Teilsicht).
     *
     * @param  Collection<int, array<string, mixed>>  $changes
     * @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}
     */
    private function importDelta(MsgraphTaskListLink $link, Collection $changes): array {
        $counters = self::emptyCounters();

        $removedIds = $changes
            ->filter(fn (array $t): bool => isset($t['@removed']))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->filter(fn (string $id): bool => $id !== '')
            ->values();
        $active = $changes->filter(fn (array $t): bool => ! isset($t['@removed']))->values();

        /** @var Collection<string, ExternalReference> $references */
        $references = ExternalReference::query()
            ->forPlugin($link->organization_id, MsgraphPlugin::ID, MsgraphPlugin::EXT_TYPE_TODO_TASK)
            ->whereIn('external_id', $active->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->get()
            ->keyBy('external_id');

        foreach ($active as $remote) {
            try {
                $counters[$this->importOne($link, $remote, $references)]++;
            } catch (Throwable) {
                $counters['failed']++;
            }
        }

        // Teilsicht: NUR explizit gemeldete Löschungen markieren.
        foreach ($removedIds as $externalId) {
            $reference = ExternalReference::query()
                ->forPlugin($link->organization_id, MsgraphPlugin::ID, MsgraphPlugin::EXT_TYPE_TODO_TASK)
                ->where('external_id', $externalId)
                ->first();
            if ($reference !== null
                && ($reference->payload['list_id'] ?? null) === $link->todo_list_id
                && $this->markRemoteDeleted($link, $reference)) {
                $counters['inbox']++;
            }
        }

        return $counters;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $remoteTasks
     * @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}
     */
    private function import(MsgraphTaskListLink $link, Collection $remoteTasks): array {
        $counters = self::emptyCounters();

        /** @var Collection<string, ExternalReference> $references */
        $references = ExternalReference::query()
            ->forPlugin($link->organization_id, MsgraphPlugin::ID, MsgraphPlugin::EXT_TYPE_TODO_TASK)
            ->whereIn('external_id', $remoteTasks->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->get()
            ->keyBy('external_id');

        foreach ($remoteTasks as $remote) {
            try {
                $counters[$this->importOne($link, $remote, $references)]++;
            } catch (Throwable) {
                $counters['failed']++;
            }
        }

        // Vollständige Sicht: verschwundene Aufgaben nur MARKIEREN (Inbox).
        $this->flagRemoteDeletions($link, $remoteTasks, $counters);

        return $counters;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  Collection<string, ExternalReference>  $references
     * @return 'created'|'updated'|'unchanged'|'conflicts'|'inbox'
     */
    private function importOne(MsgraphTaskListLink $link, array $remote, Collection $references): string {
        $externalId = (string) ($remote['id'] ?? '');
        if ($externalId === '') {
            throw new \RuntimeException('To-Do-Task ohne ID.');
        }
        $reference = $references->get($externalId);
        $mapped = $this->mapRemote($remote);

        if ($reference === null) {
            // In suppressed() gekapselt, damit der created-Export-Trigger die
            // Import-Übernahme nicht als Echo zurück nach To Do spiegelt.
            $task = MsgraphTodoTaskObserver::suppressed(fn (): Task => Task::query()->create([
                'organization_id' => $link->organization_id,
                'project_id' => $link->target_kind === MsgraphTaskListLink::KIND_PROJECT ? $link->project_id : null,
                'is_global' => $link->target_kind === MsgraphTaskListLink::KIND_GLOBAL_KANBAN,
                'title' => $mapped['title'],
                'description' => $mapped['description'],
                'status' => $mapped['status'],
                'priority' => $mapped['priority'],
                'due_date' => $mapped['due_date'],
            ]));

            $references->put($externalId, ExternalReference::query()->create([
                'organization_id' => $link->organization_id,
                'plugin_id' => MsgraphPlugin::ID,
                'external_type' => MsgraphPlugin::EXT_TYPE_TODO_TASK,
                'external_id' => $externalId,
                'referenceable_type' => $task->getMorphClass(),
                'referenceable_id' => $task->getKey(),
                'synced_at' => now(),
                'payload' => [
                    'base' => $this->baseFrom($task),
                    'list_id' => $link->todo_list_id,
                    'done_origin' => $mapped['status'] === TaskStatus::Done->value ? 'todo' : null,
                ],
            ]));

            return 'created';
        }

        $task = $reference->referenceable;
        if (! $task instanceof Task) {
            // Lokal gelöscht, remote existiert weiter → sichtbarer Inbox-Fall.
            return $this->stageInbox($link, $remote, 'local_deleted');
        }

        return $this->applyThreeWay($link, $reference, $task, $remote, $mapped);
    }

    /**
     * 3-Wege-Übernahme je Feld: base vs. lokal vs. remote — nur remote
     * geänderte Felder fließen; beidseitig geändert + ungleich ⇒ Konflikt.
     *
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $mapped
     * @return 'updated'|'unchanged'|'conflicts'
     */
    private function applyThreeWay(MsgraphTaskListLink $link, ExternalReference $reference, Task $task, array $remote, array $mapped): string {
        $payload = (array) ($reference->payload ?? []);
        $base = (array) ($payload['base'] ?? []);

        $changes = [];
        $conflicts = [];
        foreach (self::SYNCED_FIELDS as $field) {
            $remoteValue = $mapped[$field];
            $baseValue = $base[$field] ?? null;
            $localValue = $this->localValue($task, $field);

            $remoteChanged = $this->differs($remoteValue, $baseValue);
            $localChanged = $this->differs($localValue, $baseValue);

            if (! $remoteChanged) {
                continue; // lokale Änderungen exportiert ggf. der Export-Zweig
            }
            if ($localChanged && $this->differs($remoteValue, $localValue)) {
                $conflicts[$field] = ['local' => $localValue, 'remote' => $remoteValue];

                continue;
            }
            $changes[$field] = $remoteValue;
        }

        // Reopen-Regel (Todoist-Muster): „wieder geöffnet" setzt nur zurück,
        // wenn der lokale done-Stand aus derselben To-Do-Synchronisation kam.
        if (($changes['status'] ?? null) === TaskStatus::Open->value
            && $task->status === TaskStatus::Done
            && ($payload['done_origin'] ?? null) !== 'todo') {
            unset($changes['status']);
        }

        if ($conflicts !== []) {
            $this->stageConflict($link, $reference, $task, $remote, $conflicts);
        }

        if ($changes === [] && $conflicts === []) {
            return 'unchanged';
        }

        if ($changes !== []) {
            MsgraphTodoTaskObserver::suppressed(fn () => $task->forceFill($changes)->save());
        }

        // base nur für übernommene Felder fortschreiben — Konfliktfelder
        // behalten die alte Basis (sonst Phantom-Konflikte).
        $newBase = $this->baseFrom($task->refresh());
        foreach (array_keys($conflicts) as $field) {
            $newBase[$field] = $base[$field] ?? null;
        }
        $doneOrigin = ($changes['status'] ?? null) === TaskStatus::Done->value ? 'todo' : ($payload['done_origin'] ?? null);
        if (($changes['status'] ?? null) === TaskStatus::Open->value) {
            $doneOrigin = null;
        }
        unset($payload['remote_deleted_at']); // remote (wieder) vorhanden
        $reference->forceFill([
            'payload' => ['base' => $newBase, 'list_id' => $link->todo_list_id, 'done_origin' => $doneOrigin] + $payload,
            'synced_at' => now(),
        ])->save();

        return $conflicts !== [] ? 'conflicts' : 'updated';
    }

    // ── Export: WorkDiary → To Do (im Sync-Lauf + live via Outbox) ──────

    /**
     * Live-Export einer einzelnen Aufgabe ({@see MsgraphOutboxDispatcher},
     * Folgeausbau): exakt die Export-Logik des Sync-Laufs — base-Diff,
     * Konflikt-Sperre, remote-gelöscht-Markierung, Create mit linkedResource.
     *
     * @return 'created'|'updated'|'unchanged'|null
     */
    public function exportTask(MsgraphTaskListLink $link, MsgraphTaskConnection $connection, Task $task): ?string {
        $reference = ExternalReference::query()
            ->forPlugin($link->organization_id, MsgraphPlugin::ID, MsgraphPlugin::EXT_TYPE_TODO_TASK)
            ->forReferenceable($task)
            ->first();

        return $this->exportOne($link, new MsgraphTodoClient($connection), $task, $reference);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function export(MsgraphTaskListLink $link, MsgraphTodoClient $client): array {
        $counters = self::emptyCounters();

        $tasks = Task::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $link->organization_id)
            ->when(
                $link->target_kind === MsgraphTaskListLink::KIND_PROJECT,
                fn ($q) => $q->where('project_id', $link->project_id),
                fn ($q) => $q->where('is_global', true),
            )
            ->get();

        /** @var Collection<int|string, ExternalReference> $references */
        $references = ExternalReference::query()
            ->forPlugin($link->organization_id, MsgraphPlugin::ID, MsgraphPlugin::EXT_TYPE_TODO_TASK)
            ->whereIn('referenceable_id', $tasks->modelKeys())
            ->where('referenceable_type', (new Task())->getMorphClass())
            ->get()
            ->keyBy('referenceable_id');

        foreach ($tasks as $task) {
            try {
                $outcome = $this->exportOne($link, $client, $task, $references->get($task->getKey()));
                if ($outcome !== null) {
                    $counters[$outcome]++;
                }
            } catch (Throwable) {
                $counters['failed']++;
            }
        }

        return $counters;
    }

    /** @return 'created'|'updated'|'unchanged'|null */
    private function exportOne(MsgraphTaskListLink $link, MsgraphTodoClient $client, Task $task, ?ExternalReference $reference): ?string {
        if ($reference === null) {
            // Neu exportieren — mit linkedResource zurück nach WorkDiary
            // (Projektseite bzw. App-Start; eine Task-Detail-Route existiert nicht).
            $webUrl = $link->target_kind === MsgraphTaskListLink::KIND_PROJECT && $link->project_id !== null
                ? route('projects.show', $link->project_id)
                : (string) config('app.url');
            $externalId = $client->createTask($link->todo_list_id, $this->remotePayload($task) + [
                'linkedResources' => [[
                    'webUrl' => $webUrl,
                    'applicationName' => 'WorkDiary',
                    'displayName' => (string) $task->title,
                ]],
            ]);

            ExternalReference::query()->create([
                'organization_id' => $link->organization_id,
                'plugin_id' => MsgraphPlugin::ID,
                'external_type' => MsgraphPlugin::EXT_TYPE_TODO_TASK,
                'external_id' => $externalId,
                'referenceable_type' => $task->getMorphClass(),
                'referenceable_id' => $task->getKey(),
                'synced_at' => now(),
                'payload' => [
                    'base' => $this->baseFrom($task),
                    'list_id' => $link->todo_list_id,
                    'done_origin' => $task->status === TaskStatus::Done ? 'local' : null,
                ],
            ]);

            return 'created';
        }

        $payload = (array) ($reference->payload ?? []);
        $base = (array) ($payload['base'] ?? []);

        // Offener Konflikt ⇒ dieses Feldset nicht exportieren (kein
        // Last-write-wins durch die Hintertür); die Inbox löst zuerst auf.
        $hasOpenConflict = IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $link->organization_id)
            ->where('plugin_id', MsgraphPlugin::ID)
            ->where('dedupe_key', 'todo-task-conflict:' . $reference->external_id)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->exists();
        if ($hasOpenConflict) {
            return null;
        }

        $changed = false;
        foreach (self::SYNCED_FIELDS as $field) {
            if ($this->differs($this->localValue($task, $field), $base[$field] ?? null)) {
                $changed = true;

                break;
            }
        }
        if (! $changed) {
            return 'unchanged';
        }

        if (! $client->updateTask($link->todo_list_id, (string) $reference->external_id, $this->remotePayload($task))) {
            // Remote gelöscht → Marker + Inbox (Import-Löschsemantik).
            $this->markRemoteDeleted($link, $reference);

            return null;
        }

        $reference->forceFill([
            'payload' => ['base' => $this->baseFrom($task), 'list_id' => $link->todo_list_id] + $payload,
            'synced_at' => now(),
        ])->save();

        return 'updated';
    }

    // ── Feldadapter ─────────────────────────────────────────────────────

    /**
     * To Do → WorkDiary.
     *
     * @param  array<string, mixed>  $remote
     * @return array{title: string, description: string|null, status: string, priority: string, due_date: string|null}
     */
    private function mapRemote(array $remote): array {
        $body = (array) ($remote['body'] ?? []);
        $content = trim((string) ($body['content'] ?? ''));
        if (strcasecmp((string) ($body['contentType'] ?? ''), 'html') === 0) {
            $content = trim(strip_tags($content));
        }

        $due = null;
        if (isset($remote['dueDateTime']['dateTime']) && is_string($remote['dueDateTime']['dateTime'])) {
            $due = substr($remote['dueDateTime']['dateTime'], 0, 10);
        }

        return [
            'title' => (string) (($remote['title'] ?? '') !== '' ? $remote['title'] : '—'),
            'description' => $content !== '' ? $content : null,
            'status' => match ((string) ($remote['status'] ?? '')) {
                'completed' => TaskStatus::Done->value,
                'inProgress' => TaskStatus::InProgress->value,
                default => TaskStatus::Open->value,
            },
            'priority' => match ((string) ($remote['importance'] ?? 'normal')) {
                'high' => TaskPriority::High->value,
                'low' => TaskPriority::Low->value,
                default => TaskPriority::Medium->value,
            },
            'due_date' => $due,
        ];
    }

    /**
     * WorkDiary → To Do (PATCH-/POST-Payload).
     *
     * @return array<string, mixed>
     */
    private function remotePayload(Task $task): array {
        $payload = [
            'title' => (string) $task->title,
            'body' => ['contentType' => 'text', 'content' => (string) ($task->description ?? '')],
            'importance' => match ($task->priority) {
                TaskPriority::High, TaskPriority::Urgent => 'high',
                TaskPriority::Low => 'low',
                default => 'normal',
            },
            'status' => match ($task->status) {
                TaskStatus::Done => 'completed',
                TaskStatus::InProgress => 'inProgress',
                default => 'notStarted',
            },
        ];

        $due = $this->localValue($task, 'due_date');
        $payload['dueDateTime'] = is_string($due) && $due !== ''
            ? ['dateTime' => $due . 'T00:00:00.0000000', 'timeZone' => 'UTC']
            : null;

        return $payload;
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

    // ── Lösch-/Inbox-Semantik (Todoist-Muster) ──────────────────────────

    /**
     * @param  Collection<int, array<string, mixed>>  $remoteTasks
     * @param  array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}  $counters
     */
    private function flagRemoteDeletions(MsgraphTaskListLink $link, Collection $remoteTasks, array &$counters): void {
        $remoteIds = $remoteTasks->pluck('id')->map(fn ($id) => (string) $id)->all();

        $vanished = ExternalReference::query()
            ->forPlugin($link->organization_id, MsgraphPlugin::ID, MsgraphPlugin::EXT_TYPE_TODO_TASK)
            ->whereNotIn('external_id', $remoteIds)
            ->get()
            ->filter(fn (ExternalReference $ref): bool => (($ref->payload['list_id'] ?? null) === $link->todo_list_id));

        foreach ($vanished as $reference) {
            if ($this->markRemoteDeleted($link, $reference)) {
                $counters['inbox']++;
            }
        }
    }

    /** @return bool true, wenn ein NEUER Inbox-Fall entstanden ist */
    private function markRemoteDeleted(MsgraphTaskListLink $link, ExternalReference $reference): bool {
        $task = $reference->referenceable;
        if (! $task instanceof Task) {
            return false; // beidseitig weg → nichts aufzulösen
        }
        // Erledigte bleiben außen vor: To Do liefert completed weiterhin in der
        // Liste — Fehlen ist hier also ein echtes Löschsignal, aber lokale
        // done-Aufgaben erzeugen keinen Handlungsbedarf.
        if ($task->status === TaskStatus::Done) {
            return false;
        }

        $payload = (array) ($reference->payload ?? []);
        if (! isset($payload['remote_deleted_at'])) {
            $payload['remote_deleted_at'] = now()->toIso8601String();
            $reference->forceFill(['payload' => $payload])->save();
        }

        $item = IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organization_id,
            'plugin_id' => MsgraphPlugin::ID,
            'dedupe_key' => 'todo-task:' . $reference->external_id . ':remote_deleted',
        ], [
            'source' => MsgraphPlugin::ID,
            'target_type' => $task->getMorphClass(),
            'external_type' => MsgraphPlugin::EXT_TYPE_TODO_TASK,
            'external_id' => $reference->external_id,
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'local_snapshot' => $this->baseFrom($task),
            'remote_snapshot' => [],
            'display_title' => (string) $task->title,
            'display_subtitle' => (string) ($link->todo_list_name ?? $link->todo_list_id),
            'occurred_at' => now(),
        ]);

        return $item->wasRecentlyCreated;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return 'inbox'
     */
    private function stageInbox(MsgraphTaskListLink $link, array $remote, string $case): string {
        IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organization_id,
            'plugin_id' => MsgraphPlugin::ID,
            'dedupe_key' => 'todo-task:' . (string) ($remote['id'] ?? '') . ':' . $case,
        ], [
            'source' => MsgraphPlugin::ID,
            'target_type' => (new Task())->getMorphClass(),
            'external_type' => MsgraphPlugin::EXT_TYPE_TODO_TASK,
            'external_id' => (string) ($remote['id'] ?? ''),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => $remote,
            'display_title' => (string) (($remote['title'] ?? '') !== '' ? $remote['title'] : '—'),
            'display_subtitle' => (string) ($link->todo_list_name ?? $link->todo_list_id),
            'occurred_at' => now(),
        ]);

        return 'inbox';
    }

    /**
     * @param  array<string, mixed>  $remote
     * @param  array<string, array{local: mixed, remote: mixed}>  $conflicts
     */
    private function stageConflict(MsgraphTaskListLink $link, ExternalReference $reference, Task $task, array $remote, array $conflicts): void {
        IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organization_id,
            'plugin_id' => MsgraphPlugin::ID,
            'dedupe_key' => 'todo-task-conflict:' . $reference->external_id,
        ], [
            'source' => MsgraphPlugin::ID,
            'target_type' => $task->getMorphClass(),
            'external_type' => MsgraphPlugin::EXT_TYPE_TODO_TASK,
            'external_id' => $reference->external_id,
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'remote_snapshot' => $remote,
            'local_snapshot' => $this->baseFrom($task),
            'mapped_snapshot' => collect($conflicts)->map(fn (array $c) => $c['remote'])->all(),
            'diff_fields' => array_keys($conflicts),
            'display_title' => (string) $task->title,
            'display_subtitle' => (string) ($link->todo_list_name ?? $link->todo_list_id),
            'occurred_at' => now(),
        ]);
    }
}
