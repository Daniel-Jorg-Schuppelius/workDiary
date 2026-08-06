<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Models\{IntegrationOutboxEntry, MsgraphTaskConnection, MsgraphTaskListLink, Task};
use RuntimeException;

/**
 * Überträgt WorkDiary-geführte Aufgabenänderungen live nach Microsoft To Do
 * (Feature 102, Folgeausbau). Beide Operationen laufen über
 * {@see MsgraphTodoSyncService::exportTask()} — dieselbe 3-Wege-Logik wie der
 * Sync-Lauf (base-Diff, Konflikt-Sperre über die Integrations-Inbox,
 * remote-gelöscht-Markierung), damit Live-Spiegelung und Polling nie
 * auseinanderlaufen. Löschungen werden nie weitergegeben.
 */
class MsgraphOutboxDispatcher implements IntegrationOutboxDispatcher {
    public const OP_TODO_TASK_CREATE = 'todo-task.create';

    public const OP_TODO_TASK_UPDATE = 'todo-task.update';

    public function pluginId(): string {
        return \App\Plugins\Msgraph\MsgraphPlugin::ID;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        return match ($entry->operation) {
            self::OP_TODO_TASK_CREATE, self::OP_TODO_TASK_UPDATE => $this->dispatchTodoExport($entry),
            default => throw new RuntimeException('Unbekannte Msgraph-Outbox-Operation: ' . $entry->operation),
        };
    }

    /**
     * Exportiert den AKTUELLEN lokalen Stand der Aufgabe (nicht den Stand zum
     * Enqueue-Zeitpunkt — spätere Änderungen sind damit automatisch enthalten,
     * Doppel-Einträge konvergieren idempotent über den base-Diff).
     */
    private function dispatchTodoExport(IntegrationOutboxEntry $entry): bool {
        $payload = $entry->payload;
        $task = Task::query()->withoutGlobalScopes()->find((int) ($payload['task_id'] ?? $entry->subject_id ?? 0));
        if (! $task instanceof Task) {
            return true; // lokal gelöscht → keine Löschweitergabe, nichts zu übertragen
        }

        $link = MsgraphTaskListLink::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->when($task->project_id !== null, fn ($q) => $q
                ->where('target_kind', MsgraphTaskListLink::KIND_PROJECT)
                ->where('project_id', $task->project_id))
            ->when($task->project_id === null, fn ($q) => $q
                ->where('target_kind', MsgraphTaskListLink::KIND_GLOBAL_KANBAN))
            ->first();
        if ($link === null || ! $link->exportsToTodo() || $link->status !== MsgraphTaskListLink::STATUS_ACTIVE) {
            return true; // Exportrichtung nicht (mehr) aktiv → bewusst kein Transfer
        }

        $connection = MsgraphTaskConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->first();
        if (! $connection instanceof MsgraphTaskConnection || ! $connection->isActive()) {
            throw new RuntimeException('Microsoft-To-Do-Verbindung inaktiv.'); // Queue wiederholt
        }

        app(MsgraphTodoSyncService::class)->exportTask($link, $connection, $task);

        return true;
    }
}
