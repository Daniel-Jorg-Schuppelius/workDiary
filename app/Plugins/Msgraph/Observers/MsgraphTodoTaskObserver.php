<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTodoTaskObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Observers;

use App\Models\{ExternalReference, MsgraphTaskConnection, MsgraphTaskListLink, Task};
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Msgraph\Services\{MsgraphOutboxDispatcher, MsgraphTodoSyncService};
use App\Services\Integration\IntegrationOutboxService;

/**
 * Live-Export-Trigger für Microsoft To Do (Feature 102, Folgeausbau —
 * Todoist-Muster {@see \App\Plugins\Todoist\Observers\TodoistTaskObserver}):
 * lokale Änderungen an synchronisierten Feldern werden NUR als Outbox-Eintrag
 * enqueued — keine Graph-Logik in Model-Events; die Übertragung läuft asynchron
 * über den {@see MsgraphOutboxDispatcher}, der die 3-Wege-Exportlogik des
 * Sync-Laufs wiederverwendet. Löschungen werden bewusst NICHT weitergegeben.
 * Während der Import-Übernahme ist der Trigger unterdrückt (kein Echo).
 */
class MsgraphTodoTaskObserver {
    private static bool $suppressed = false;

    /** Import-Übernahmen ohne Export-Echo ausführen. */
    public static function suppressed(callable $callback): mixed {
        self::$suppressed = true;
        try {
            return $callback();
        } finally {
            self::$suppressed = false;
        }
    }

    /**
     * Neue, in eine exportierende To-Do-Listen-Zuordnung fallende Aufgabe →
     * als `todo-task.create` enqueuen; importierte Aufgaben sind über
     * {@see suppressed()} ausgenommen (kein Echo-Create).
     */
    public function created(Task $task): void {
        if (self::$suppressed) {
            return;
        }

        $link = $this->exportingLink($task);
        if ($link === null || ! $this->connectionActive($task)) {
            return;
        }

        app(IntegrationOutboxService::class)->enqueue(
            (int) $task->organization_id,
            MsgraphPlugin::ID,
            MsgraphOutboxDispatcher::OP_TODO_TASK_CREATE,
            ['task_id' => $task->id],
            'msgraph-todo-create:' . $task->id,
            $task,
        );
    }

    public function updated(Task $task): void {
        if (self::$suppressed) {
            return;
        }

        $changed = array_values(array_intersect(MsgraphTodoSyncService::SYNCED_FIELDS, array_keys($task->getChanges())));
        if ($changed === []) {
            return;
        }

        if (! $this->connectionActive($task)) {
            return;
        }

        // Nur bereits verknüpfte Aufgaben live spiegeln — unverknüpfte holt
        // der stündliche Sync-Lauf nach (Todoist-Semantik).
        $linked = ExternalReference::query()
            ->forPlugin($task->organization_id, MsgraphPlugin::ID, MsgraphPlugin::EXT_TYPE_TODO_TASK)
            ->forReferenceable($task)
            ->exists();
        if (! $linked) {
            return;
        }

        $link = $this->exportingLink($task);
        if ($link === null) {
            return;
        }

        app(IntegrationOutboxService::class)->enqueue(
            (int) $task->organization_id,
            MsgraphPlugin::ID,
            MsgraphOutboxDispatcher::OP_TODO_TASK_UPDATE,
            ['task_id' => $task->id, 'fields' => $changed],
            'msgraph-todo:' . $task->id . ':' . now()->format('YmdHisu'),
            $task,
        );
    }

    /** Nur aktive Verbindungen — Pausieren/Trennen stoppt den Export bewusst. */
    private function connectionActive(Task $task): bool {
        $connection = MsgraphTaskConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->first();

        return $connection instanceof MsgraphTaskConnection && $connection->isActive();
    }

    /** Aktive, exportierende Listen-Zuordnung der Aufgabe, sonst null. */
    private function exportingLink(Task $task): ?MsgraphTaskListLink {
        $link = MsgraphTaskListLink::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->when($task->project_id !== null, fn ($q) => $q
                ->where('target_kind', MsgraphTaskListLink::KIND_PROJECT)
                ->where('project_id', $task->project_id))
            ->when($task->project_id === null, fn ($q) => $q
                ->where('target_kind', MsgraphTaskListLink::KIND_GLOBAL_KANBAN))
            ->first();

        return $link !== null && $link->exportsToTodo() && $link->status === MsgraphTaskListLink::STATUS_ACTIVE
            ? $link
            : null;
    }
}
