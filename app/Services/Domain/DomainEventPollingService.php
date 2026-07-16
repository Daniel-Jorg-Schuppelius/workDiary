<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainEventPollingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\DomainCapabilityArea;
use App\Models\Domain\{DomainEvent, DomainProviderConnection};
use App\Plugins\Support\Domain\DomainProviderException;
use Illuminate\Support\Carbon;

/**
 * Ereignis-Polling (Feature 083, MVP-391). `QueryEventList` liefert die
 * offenen Ereignisse; jedes wird ZUERST dauerhaft gespeichert und ERST DANN
 * über `DeleteEvent` quittiert. Schlägt der Acknowledge fehl, bleibt das
 * Ereignis „stored" und wird beim nächsten Lauf erneut quittiert (Dedup über
 * die Event-ID) — kein Datenverlust, kein blinder Wiederholungslauf.
 */
class DomainEventPollingService {
    public function __construct(private readonly DomainProviderResolver $resolver) {}

    /**
     * @return array{stored: int, acknowledged: int}
     */
    public function poll(DomainProviderConnection $connection, int $limit = 100): array {
        $adapter = $this->resolver->for($connection);
        $response = $adapter->execute('QueryEventList', ['limit' => $limit], DomainCapabilityArea::Events);

        $stored = 0;
        $acknowledged = 0;

        foreach ($response->rows() as $row) {
            $eventId = $row['eventid'] ?? $row['id'] ?? null;
            if ($eventId === null || $eventId === '') {
                continue;
            }

            $event = DomainEvent::query()->firstOrNew([
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'external_event_id' => $eventId,
            ]);
            $isNew = ! $event->exists;

            $event->fill([
                'event_class' => $row['class'] ?? $row['eventclass'] ?? null,
                'event_action' => $row['action'] ?? $row['eventaction'] ?? null,
                'object' => $row['object'] ?? null,
                'raw_hash' => hash('sha256', json_encode($row) ?: ''),
                'occurred_at' => isset($row['eventdate']) ? Carbon::parse($row['eventdate'], 'UTC') : null,
            ]);
            if ($isNew) {
                $event->status = 'stored';
                $event->stored_at = Carbon::now();
                $stored++;
            }
            $event->save(); // DURABLE STORE zuerst

            if ($event->status !== 'acknowledged') {
                try {
                    $ack = $adapter->execute('DeleteEvent', ['event' => $eventId], DomainCapabilityArea::Events);
                    if ($ack->isSuccess()) {
                        $event->forceFill(['status' => 'acknowledged', 'acknowledged_at' => Carbon::now()])->save();
                        $acknowledged++;
                    }
                } catch (DomainProviderException) {
                    // Acknowledge fehlgeschlagen → „stored" belassen, nächster Lauf quittiert erneut.
                }
            }
        }

        return ['stored' => $stored, 'acknowledged' => $acknowledged];
    }
}
