<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractTaskSyncService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, IntegrationInboxItem, Task};
use Illuminate\Support\Collection;

/**
 * Gemeinsamer 3-Wege-Sync-Kern der Aufgaben-Plugins (Vollscan 2026-08-23,
 * B8/Welle 3) nach dem Skeleton-Muster von
 * {@see \App\Plugins\Support\GitIssueImport\AbstractGitIssueImporter}:
 * Todoist (Feature 055) und Microsoft To Do (Feature 102) teilen den
 * base-Snapshot-Abgleich (base vs. lokal vs. remote, nie Last-write-wins),
 * die Reopen-Regel (done_origin), die Lösch-MARKIERUNG (nie Löschweitergabe)
 * und die Inbox-/Konfliktfälle. Referenz-Typen und Dedupe-Keys liefern die
 * Subklassen unverändert (Bestandsdaten!).
 *
 * Bewusste Verhaltensunterschiede der Subklassen (Hooks — NICHT angleichen):
 * - Subtasks: nur Todoist führt `parent_id` (Eltern-Auflösung, orphan_subtask-
 *   Inbox; `parent_task_id` wird nur zusammen mit Feldänderungen übernommen);
 *   To Do kennt keinen Task-Baum (Checklist-Items) → `$parentTaskId = null`.
 * - Feldadapter (bleiben Subklasse): Todoist mappt über sectionMap
 *   (Abschnitt→Status) und collaboratorMap (Bearbeiter), SYNCED_FIELDS
 *   zusätzlich time_budget + assigned_to; To Do nur 5 Kernfelder,
 *   HTML-Body wird zu Text gestrippt.
 * - Löschsemantik: Todoist markiert bei EXPLIZITEM Löschsignal (Delta
 *   `is_deleted` ⇒ `explicit: true`) auch lokal erledigte Aufgaben; To Do nie
 *   (completed bleibt in der To-Do-Liste ⇒ done erzeugt keinen
 *   Handlungsbedarf, auch bei `@removed` bleibt `explicit` false).
 * - Link-Zugehörigkeit: Todoist prüft die AUFGABE in markRemoteDeleted
 *   ({@see taskBelongsToLink()}: project_id bzw. is_global); To Do prüft die
 *   REFERENZ vor dem Aufruf ({@see referenceBelongsToLink()}: payload.list_id).
 * - Referenz-Payload beim Update: Todoist persistiert remote-Snapshot +
 *   section_id, To Do nur list_id (KEIN remote-Snapshot); done_origin-Marker
 *   'todoist' vs. 'todo' ({@see doneOriginMarker()}).
 * - Dedupe-Keys (Bestandsdaten — nie ändern): 'task…' vs. 'todo-task…'
 *   ({@see dedupePrefix()}); leerer Remote-Titel im Inbox-display_title:
 *   Todoist lässt '' durch, To Do ersetzt durch '—'
 *   ({@see remoteDisplayTitle()}).
 *
 * @template TLink of TaskSyncLink
 */
abstract class AbstractTaskSyncService {
    /** Plugin-ID — zugleich `source` der Inbox-Fälle (beide Plugins identisch). */
    abstract protected function pluginId(): string;

    /** ExternalReference-`external_type` (Bestandsdaten — nie ändern). */
    abstract protected function externalType(): string;

    /** Präfix der Inbox-Dedupe-Keys, z. B. `task` (Bestandsdaten — nie ändern). */
    abstract protected function dedupePrefix(): string;

    /** done_origin-Marker der eigenen Synchronisation (Reopen-Regel). */
    abstract protected function doneOriginMarker(): string;

    /** Felder des gemeinsamen base-Snapshots (Konfliktbasis). */
    /** @return list<string> */
    abstract protected function syncedFields(): array;

    /** Untertitel der Inbox-Fälle (Projekt- bzw. Listenname). */
    /** @param TLink $link */
    abstract protected function displaySubtitle(TaskSyncLink $link): string;

    /** Titel eines Remote-Items für Inbox-Fälle ohne lokale Aufgabe. */
    /** @param array<string, mixed> $remote */
    abstract protected function remoteDisplayTitle(array $remote): string;

    /** Führt den Write aus, ohne dass der Export-Observer ihn zurückspiegelt. */
    abstract protected function withoutExportEcho(callable $callback): mixed;

    /**
     * Frischer Payload-Anteil des ExternalReference nach einer 3-Wege-Übernahme
     * (wird per `+ $payload` über den Bestand gelegt; `base` muss enthalten sein).
     *
     * @param  TLink  $link
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $newBase
     * @return array<string, mixed>
     */
    abstract protected function updatedReferencePayload(TaskSyncLink $link, array $remote, array $newBase, mixed $doneOrigin): array;

    /** Gehört die Referenz zu dieser Zuordnung (Voll-Abgleich-Filter)? */
    /** @param TLink $link */
    protected function referenceBelongsToLink(TaskSyncLink $link, ExternalReference $reference): bool {
        return true;
    }

    /** Gehört die lokale Aufgabe zu dieser Zuordnung (Lösch-Markierung)? */
    /** @param TLink $link */
    protected function taskBelongsToLink(TaskSyncLink $link, Task $task): bool {
        return true;
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    public static function emptyCounters(): array {
        return ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0, 'inbox' => 0, 'failed' => 0];
    }

    /**
     * 3-Wege-Übernahme je Feld: base vs. lokal vs. remote. Nur remote
     * geänderte Felder fließen; beidseitig geändert + ungleich → Konflikt-
     * Inbox-Item mit diff_fields (MVP-114-Basis). `$parentTaskId` (nur
     * Todoist) wird ausschließlich zusammen mit Feldänderungen übernommen.
     *
     * @param  TLink  $link
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $mapped
     * @return 'updated'|'unchanged'|'conflicts'
     */
    protected function applyThreeWay(TaskSyncLink $link, ExternalReference $reference, Task $task, array $remote, array $mapped, ?int $parentTaskId = null): string {
        $payload = (array) ($reference->payload ?? []);
        $base = (array) ($payload['base'] ?? []);

        $changes = [];
        $conflicts = [];
        foreach ($this->syncedFields() as $field) {
            if (! array_key_exists($field, $mapped)) {
                continue; // Feld wird in diesem Lauf nicht geführt (z. B. Status ohne Abschnittszuordnung)
            }
            $remoteValue = $mapped[$field];
            $baseValue = $base[$field] ?? null;
            $localValue = $this->localValue($task, $field);

            $remoteChanged = $this->differs($remoteValue, $baseValue);
            $localChanged = $this->differs($localValue, $baseValue);

            if (! $remoteChanged) {
                continue; // remote unverändert → lokale Änderungen exportiert ggf. der Export-Zweig
            }
            if ($localChanged && $this->differs($remoteValue, $localValue)) {
                $conflicts[$field] = ['local' => $localValue, 'remote' => $remoteValue];

                continue;
            }
            $changes[$field] = $remoteValue;
        }

        // Reopen-Regel: „wieder geöffnet" setzt nur zurück, wenn der lokale
        // done-Stand aus derselben Synchronisation stammte (done_origin).
        if (($changes['status'] ?? null) === TaskStatus::Open->value
            && $task->status === TaskStatus::Done
            && ($payload['done_origin'] ?? null) !== $this->doneOriginMarker()) {
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
            $this->withoutExportEcho(fn () => $task->forceFill($changes)->save());
        }

        // base NUR für erfolgreich übernommene Felder fortschreiben —
        // Konfliktfelder behalten die alte Basis (sonst Phantom-Konflikte).
        $newBase = $this->baseFrom($task->refresh());
        foreach (array_keys($conflicts) as $field) {
            $newBase[$field] = $base[$field] ?? null;
        }
        $doneOrigin = ($changes['status'] ?? null) === TaskStatus::Done->value ? $this->doneOriginMarker() : ($payload['done_origin'] ?? null);
        if (($changes['status'] ?? null) === TaskStatus::Open->value) {
            $doneOrigin = null;
        }
        unset($payload['remote_deleted_at']); // Aufgabe ist remote (wieder) vorhanden
        $reference->forceFill([
            'payload' => $this->updatedReferencePayload($link, $remote, $newBase, $doneOrigin) + $payload,
            'synced_at' => now(),
        ])->save();

        return $conflicts !== [] ? 'conflicts' : 'updated';
    }

    /** @return array<string, mixed> */
    protected function baseFrom(Task $task): array {
        $base = [];
        foreach ($this->syncedFields() as $field) {
            $base[$field] = $this->localValue($task, $field);
        }

        return $base;
    }

    protected function localValue(Task $task, string $field): mixed {
        $value = $task->getAttribute($field);
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    protected function differs(mixed $a, mixed $b): bool {
        return ($a === null ? null : (string) $a) !== ($b === null ? null : (string) $b);
    }

    /**
     * Löschsemantik (MVP-114): remote verschwundene Aufgaben werden NIE
     * automatisch gelöscht — nur `remote_deleted_at`-Marker am
     * ExternalReference + sichtbarer Inbox-Fall (lokal archivieren /
     * entkoppeln / neu anlegen entscheidet der Mensch). Nur bei VOLLSTÄNDIGER
     * Remote-Sicht aufrufen — ein Delta ist keine Abwesenheitsaussage.
     *
     * @param  TLink  $link
     * @param  Collection<int, array<string, mixed>>  $remoteTasks
     * @param  array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int}  $counters
     */
    protected function flagRemoteDeletions(TaskSyncLink $link, Collection $remoteTasks, array &$counters): void {
        $remoteIds = $remoteTasks->pluck('id')->map(fn ($id) => (string) $id)->all();

        $vanished = ExternalReference::query()
            ->forPlugin($link->organizationId(), $this->pluginId(), $this->externalType())
            ->whereNotIn('external_id', $remoteIds)
            ->get()
            ->filter(fn (ExternalReference $reference): bool => $this->referenceBelongsToLink($link, $reference));

        foreach ($vanished as $reference) {
            if ($this->markRemoteDeleted($link, $reference, explicit: false)) {
                $counters['inbox']++;
            }
        }
    }

    /**
     * Marker + Inbox-Fall für eine remote verschwundene/gelöschte Aufgabe.
     * `explicit` = ausdrückliches Löschsignal (Todoist-Delta `is_deleted`) —
     * dann wird auch eine lokal erledigte Aufgabe markiert; ohne Signal
     * bleiben erledigte außen vor (Fehlen könnte bloße Erledigung sein).
     *
     * @param  TLink  $link
     * @return bool true, wenn ein NEUER Inbox-Fall entstanden ist
     */
    protected function markRemoteDeleted(TaskSyncLink $link, ExternalReference $reference, bool $explicit = false): bool {
        $task = $reference->referenceable;
        if (! $task instanceof Task) {
            return false; // beidseitig weg → nichts aufzulösen
        }
        if (! $this->taskBelongsToLink($link, $task) || (! $explicit && $task->status === TaskStatus::Done)) {
            return false;
        }

        $payload = (array) ($reference->payload ?? []);
        if (! isset($payload['remote_deleted_at'])) {
            $payload['remote_deleted_at'] = now()->toIso8601String();
            $reference->forceFill(['payload' => $payload])->save();
        }

        $item = IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organizationId(),
            'plugin_id' => $this->pluginId(),
            'dedupe_key' => $this->dedupePrefix() . ':' . $reference->external_id . ':remote_deleted',
        ], [
            'source' => $this->pluginId(),
            'target_type' => $task->getMorphClass(),
            'external_type' => $this->externalType(),
            'external_id' => $reference->external_id,
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'local_snapshot' => $this->baseFrom($task),
            'remote_snapshot' => [],
            'display_title' => (string) $task->title,
            'display_subtitle' => $this->displaySubtitle($link),
            'occurred_at' => now(),
        ]);

        return $item->wasRecentlyCreated;
    }

    /**
     * Inbox-Fall ohne 3-Wege-Abgleich (z. B. lokal gelöscht, verwaiste
     * Unteraufgabe) — nie stille Neuanlage oder Löschweitergabe.
     *
     * @param  TLink  $link
     * @param  array<string, mixed>  $remote
     * @return 'inbox'
     */
    protected function stageInbox(TaskSyncLink $link, array $remote, string $case): string {
        IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organizationId(),
            'plugin_id' => $this->pluginId(),
            'dedupe_key' => $this->dedupePrefix() . ':' . (string) ($remote['id'] ?? '') . ':' . $case,
        ], [
            'source' => $this->pluginId(),
            'target_type' => (new Task())->getMorphClass(),
            'external_type' => $this->externalType(),
            'external_id' => (string) ($remote['id'] ?? ''),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => $remote,
            'display_title' => $this->remoteDisplayTitle($remote),
            'display_subtitle' => $this->displaySubtitle($link),
            'occurred_at' => now(),
        ]);

        return 'inbox';
    }

    /**
     * Feldkonflikt (beidseitig geändert) → Inbox-Item `conflict` mit
     * diff_fields + beiden Snapshots. Die Inbox-UI bietet „lokal behalten"/
     * „remote übernehmen"; nichts wird still überschrieben.
     *
     * @param  TLink  $link
     * @param  array<string, mixed>  $remote
     * @param  array<string, array{local: mixed, remote: mixed}>  $conflicts
     */
    protected function stageConflict(TaskSyncLink $link, ExternalReference $reference, Task $task, array $remote, array $conflicts): void {
        IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $link->organizationId(),
            'plugin_id' => $this->pluginId(),
            'dedupe_key' => $this->dedupePrefix() . '-conflict:' . $reference->external_id,
        ], [
            'source' => $this->pluginId(),
            'target_type' => $task->getMorphClass(),
            'external_type' => $this->externalType(),
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
            'display_subtitle' => $this->displaySubtitle($link),
            'occurred_at' => now(),
        ]);
    }
}
