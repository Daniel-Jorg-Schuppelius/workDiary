<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitIssueWritebackObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\GitIssueImport;

use App\Models\{ExternalReference, Task};
use App\Services\Integration\{IntegrationOutboxDispatcherResolver, IntegrationOutboxService};

/**
 * Schlanker Export-Trigger der Git-Issue-Rückrichtung (Audit 2026-08,
 * Welle 1.4; Muster {@see \App\Plugins\Support\TimeWritebackObserver}):
 * Statuswechsel an Aufgaben werden je registriertem
 * {@see AbstractGitIssueWritebackDispatcher} als Outbox-Eintrag enqueued —
 * keine API-Logik in Model-Events. Während des Imports unterdrückt, damit
 * übernommene Remote-Statuswechsel kein Echo zurück erzeugen.
 */
class GitIssueWritebackObserver {
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
        if (self::$suppressed || ! array_key_exists('status', $task->getChanges())) {
            return;
        }

        foreach (app(IntegrationOutboxDispatcherResolver::class)->all() as $dispatcher) {
            if (! $dispatcher instanceof AbstractGitIssueWritebackDispatcher) {
                continue;
            }
            if (! $dispatcher->writebackEnabled((int) $task->organization_id)) {
                continue;
            }

            // Nur Issue-verknüpfte Aufgaben — sonst füllte jeder Statuswechsel
            // jeder Aufgabe die Outbox mit Drop-Einträgen.
            $linked = ExternalReference::query()
                ->forPlugin($task->organization_id, $dispatcher->pluginId(), $dispatcher->externalType())
                ->forReferenceable($task)
                ->exists();
            if (! $linked) {
                continue;
            }

            app(IntegrationOutboxService::class)->enqueue(
                (int) $task->organization_id,
                $dispatcher->pluginId(),
                AbstractGitIssueWritebackDispatcher::statusOperation($dispatcher->pluginId()),
                ['task_id' => $task->getKey()],
                // Zeitanteil im Schlüssel: jeder Statuswechsel ist ein eigener
                // Vorgang; der Dispatcher liest ohnehin den aktuellen Stand.
                $dispatcher->pluginId() . '-issue-status:' . $task->getKey() . ':' . now()->format('YmdHisu'),
                $task,
            );
        }
    }
}
