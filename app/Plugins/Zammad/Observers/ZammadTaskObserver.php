<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadTaskObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Observers;

use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, Task, ZammadConnection};
use App\Plugins\Zammad\Services\ZammadOutboxDispatcher;
use App\Plugins\Zammad\ZammadPlugin;
use App\Services\Integration\IntegrationOutboxService;

/**
 * Schlanker Rückkanal-Trigger (Feature 060, 2. Stufe): wird eine mit einem
 * Zammad-Ticket verknüpfte Aufgabe lokal auf „erledigt" gesetzt, wird NUR ein
 * Outbox-Eintrag enqueued — keine Zammad-Logik in Model-Events; die Übertragung
 * läuft asynchron über den {@see ZammadOutboxDispatcher}. Der Import erzeugt
 * Aufgaben nur (aktualisiert sie nie), daher gibt es kein Import-Echo — eine
 * Unterdrückung wie beim bidirektionalen Todoist-Export ist nicht nötig.
 */
class ZammadTaskObserver {
    public function updated(Task $task): void {
        // Nur beim Übergang auf „erledigt".
        if (! array_key_exists('status', $task->getChanges()) || $task->status !== TaskStatus::Done) {
            return;
        }

        // Rückkanal muss für die Organisation konfiguriert sein (opt-in).
        $connection = ZammadConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->get()
            ->first(static fn (ZammadConnection $c): bool => $c->pushesResolution());
        if (! $connection instanceof ZammadConnection) {
            return;
        }

        $reference = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $task->organization_id)
            ->where('plugin_id', ZammadPlugin::ID)
            ->where('external_type', ZammadPlugin::EXT_TYPE_TICKET)
            ->where('referenceable_type', $task->getMorphClass())
            ->where('referenceable_id', $task->getKey())
            ->first();
        if ($reference === null) {
            return; // nicht Zammad-verknüpft
        }

        app(IntegrationOutboxService::class)->enqueue(
            (int) $task->organization_id,
            ZammadPlugin::ID,
            ZammadOutboxDispatcher::OP_TICKET_RESOLVE,
            [
                'task_id' => $task->id,
                'ticket_id' => (int) $reference->external_id,
            ],
            'zammad:resolve:' . $task->id . ':' . now()->format('YmdHisu'),
            $task,
        );
    }
}
