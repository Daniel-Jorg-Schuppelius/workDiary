<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadTimeEntryObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Zammad\Observers;

use App\Models\{ExternalReference, Task, TimeEntry, ZammadConnection};
use App\Plugins\Zammad\Services\ZammadOutboxDispatcher;
use App\Plugins\Zammad\ZammadPlugin;
use App\Services\Integration\IntegrationOutboxService;

/**
 * Zeit-Rückkanal-Trigger (Feature 060, Rang 23): wird zu einer Aufgabe, die aus
 * einem Zammad-Ticket importiert wurde, eine Zeit erfasst, wird EIN Outbox-
 * Eintrag enqueued — die Buchung ins Ticket (`time_accountings`) läuft asynchron
 * über den {@see ZammadOutboxDispatcher}. Opt-in je Anbindung
 * ({@see ZammadConnection::pushesTime()}); idempotent je TimeEntry.
 */
class ZammadTimeEntryObserver {
    public function __construct(private readonly IntegrationOutboxService $outbox) {}

    public function created(TimeEntry $timeEntry): void {
        if ($timeEntry->task_id === null || (int) $timeEntry->minutes <= 0) {
            return;
        }

        $connection = ZammadConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $timeEntry->organization_id)
            ->get()
            ->first(static fn (ZammadConnection $c): bool => $c->pushesTime());
        if (! $connection instanceof ZammadConnection) {
            return;
        }

        $reference = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $timeEntry->organization_id)
            ->where('plugin_id', ZammadPlugin::ID)
            ->where('external_type', ZammadPlugin::EXT_TYPE_TICKET)
            ->where('referenceable_type', (new Task)->getMorphClass())
            ->where('referenceable_id', $timeEntry->task_id)
            ->first();
        if ($reference === null) {
            return; // Aufgabe nicht Zammad-verknüpft
        }

        $this->outbox->enqueue(
            (int) $timeEntry->organization_id,
            ZammadPlugin::ID,
            ZammadOutboxDispatcher::OP_TICKET_TIME,
            [
                'ticket_id' => (int) $reference->external_id,
                'minutes' => (int) $timeEntry->minutes,
                'time_entry_id' => $timeEntry->id,
            ],
            'zammad:time:' . $timeEntry->id,
            $timeEntry,
        );
    }
}
