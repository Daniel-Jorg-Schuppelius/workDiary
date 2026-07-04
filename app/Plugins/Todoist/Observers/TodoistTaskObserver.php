<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistTaskObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Observers;

use App\Models\{ExternalReference, Task, TodoistConnection, TodoistProjectLink};
use App\Plugins\Todoist\Services\{TodoistImportService, TodoistOutboxDispatcher};
use App\Plugins\Todoist\TodoistPlugin;
use App\Services\Integration\IntegrationOutboxService;

/**
 * Schlanker Export-Trigger (Feature 055, MVP-114): lokale Änderungen an
 * synchronisierten Feldern werden NUR als Outbox-Eintrag enqueued — keine
 * Todoist-Logik in Model-Events; die Übertragung läuft asynchron über den
 * {@see TodoistOutboxDispatcher}. Löschungen werden bewusst NICHT behandelt
 * (keine Löschweitergabe, kein `data:delete`-Scope). Während des Imports ist
 * der Trigger unterdrückt, damit übernommene Remote-Änderungen kein Echo
 * zurück nach Todoist erzeugen.
 */
class TodoistTaskObserver {
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

    public function updated(Task $task): void {
        if (self::$suppressed) {
            return;
        }

        $changed = array_values(array_intersect(TodoistImportService::SYNCED_FIELDS, array_keys($task->getChanges())));
        if ($changed === []) {
            return;
        }

        // Nur aktive Verbindungen — Pausieren/Trennen stoppt den Export bewusst.
        $connection = TodoistConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->first();
        if ($connection === null || ! $connection->isActive()) {
            return;
        }

        $reference = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->where('plugin_id', TodoistPlugin::ID)
            ->where('external_type', TodoistPlugin::EXT_TYPE_TASK)
            ->where('referenceable_type', $task->getMorphClass())
            ->where('referenceable_id', $task->getKey())
            ->first();
        if ($reference === null) {
            return; // nicht Todoist-verknüpft
        }

        $link = TodoistProjectLink::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->when($task->project_id !== null, fn ($q) => $q
                ->where('target_kind', TodoistProjectLink::KIND_PROJECT)
                ->where('project_id', $task->project_id))
            ->when($task->project_id === null, fn ($q) => $q
                ->where('target_kind', TodoistProjectLink::KIND_GLOBAL_KANBAN))
            ->first();
        if ($link === null || ! $link->exportsToTodoist() || $link->status !== TodoistProjectLink::STATUS_ACTIVE) {
            return;
        }

        app(IntegrationOutboxService::class)->enqueue(
            (int) $task->organization_id,
            TodoistPlugin::ID,
            TodoistOutboxDispatcher::OP_TASK_UPDATE,
            [
                'task_id' => $task->id,
                'external_id' => $reference->external_id,
                'fields' => $changed,
            ],
            'task:' . $task->id . ':' . now()->format('YmdHisu'),
            $task,
        );
    }
}
