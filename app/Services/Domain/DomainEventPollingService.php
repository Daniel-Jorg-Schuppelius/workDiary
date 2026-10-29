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

use App\Enums\Domain\{DomainCapabilityArea, DomainEventStatus};
use App\Models\Domain\{DomainEvent, DomainProviderConnection};
use App\Plugins\Support\Domain\DomainProviderException;
use App\Services\Domain\Concerns\ReadsProviderFields;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Ereignis-Polling (Feature 083, MVP-391). `QueryEventList` liefert die
 * offenen Ereignisse; jedes wird ZUERST dauerhaft gespeichert und ERST DANN
 * über `DeleteEvent` quittiert. Schlägt der Acknowledge fehl, bleibt das
 * Ereignis „stored" und wird beim nächsten Lauf erneut quittiert (Dedup über
 * die Event-ID) — kein Datenverlust, kein blinder Wiederholungslauf.
 */
class DomainEventPollingService {
    use ReadsProviderFields;

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
            // Doku: EVENT, EVENTCLASS, EVENTSUBCLASS, EVENT_DATE, OBJECT_ID — bis 2026-10-29 las der
            // Abruf `eventid`/`id` und speicherte deshalb nie ein Ereignis.
            $eventId = self::providerField($row, ['event', 'eventid', 'id']);
            if ($eventId === null) {
                continue;
            }

            $event = DomainEvent::query()->firstOrNew([
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'external_event_id' => $eventId,
            ]);
            $isNew = ! $event->exists;

            // Live-Vertrag (domain:contract 2026-10-29): die Liste trägt nur die Kennung, Klasse,
            // Unterklasse, Objekt und Datum liefert StatusEvent je Ereignis.
            if (self::providerField($row, ['eventclass', 'class']) === null) {
                try {
                    $row = array_merge($row, $this->flatten($adapter->execute('StatusEvent', ['event' => $eventId], DomainCapabilityArea::Events)));
                } catch (DomainProviderException $e) {
                    // Ohne Details bleibt die Kennung — und wird quittiert, sonst staut sich dasselbe
                    // Hundert bei jedem Abruf und kein neues Ereignis kommt mehr durch.
                    Log::warning('domain events: StatusEvent fehlgeschlagen', ['event' => $eventId, 'exception' => $e::class]);
                }
            }
            $occurredAt = self::providerField($row, ['eventdate', 'date']);
            $event->fill([
                'event_class' => self::providerField($row, ['eventclass', 'class']),
                'event_action' => self::providerField($row, ['eventsubclass', 'subclass', 'eventaction', 'action']),
                'object' => self::providerField($row, ['objectid', 'object', 'eventobject']),
                'raw_hash' => CryptoHelper::hash(JsonHelper::encode($row)),
                'occurred_at' => $occurredAt !== null ? Carbon::parse($occurredAt, 'UTC') : null,
            ]);
            if ($isNew) {
                $event->status = DomainEventStatus::Stored;
                $event->stored_at = Carbon::now();
                $stored++;
            }
            $event->save(); // DURABLE STORE zuerst

            // Vollaudit 2026-07 (H12): Transfer-Statusänderungen an die Admins —
            // Registrar-Transfers sind zeitkritisch (FOA/ACK-Fristen).
            $classAction = strtolower((string) $event->event_class . ' ' . (string) $event->event_action);
            if ($isNew && str_contains($classAction, 'transfer')) {
                app(\App\Services\Notification\NotificationDispatcher::class)->notify(
                    \App\Enums\Notification\NotificationEvent::DomainTransferChanged,
                    $event,
                    null,
                    [
                        'title' => (string) __('notification.message.domain_transfer_changed_title', ['domain' => (string) ($event->object ?? '—')]),
                        'title_key' => 'notification.message.domain_transfer_changed_title',
                        'title_params' => ['domain' => (string) ($event->object ?? '—')],
                        'message' => trim((string) $event->event_class . ' / ' . (string) $event->event_action),
                        'url' => route('domains.index'),
                    ],
                );
            }

            if ($event->status !== DomainEventStatus::Acknowledged) {
                try {
                    $ack = $adapter->execute('DeleteEvent', ['event' => $eventId], DomainCapabilityArea::Events);
                    if ($ack->isSuccess()) {
                        $event->forceFill(['status' => DomainEventStatus::Acknowledged, 'acknowledged_at' => Carbon::now()])->save();
                        $acknowledged++;
                    }
                } catch (DomainProviderException) {
                    // Acknowledge fehlgeschlagen → „stored" belassen, nächster Lauf quittiert erneut.
                }
            }
        }

        return ['stored' => $stored, 'acknowledged' => $acknowledged];
    }

    /**
     * Erste Werte aller Eigenschaften einer Einzelantwort als Zeile.
     *
     * @return array<string, string>
     */
    private function flatten(\App\Plugins\Support\Domain\DomainResponse $response): array {
        $row = [];
        foreach ($response->propertyNames() as $name) {
            $value = $response->first($name);
            if ($value !== null) {
                $row[$name] = $value;
            }
        }

        return $row;
    }
}
