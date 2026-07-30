<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteCalendarPublishService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

use App\Models\ExternalReference;
use Illuminate\Support\Carbon;

/**
 * Idempotenter Abgleich der Kalenderelemente gegen einen Kalender-Provider
 * (MVP-328, Bauturbo A8; seit C9 auch der CalDAV-Weg). Der Publish-Zustand
 * liegt in {@see ExternalReference} (Plugin-ID des Providers, `external_id`
 * = Remote-ID, Payload = Fingerprint + stabile UID):
 *
 * - neu → create (Remote-ID merken), geändert (Hash abweichend) → update;
 * - unverändert (Hash gleich) → übersprungen (kein Request);
 * - abgesagt (`cancelled`) → delete, Referenz entfernt;
 * - Gateway-Fehler → `failed`, Referenz **unverändert** (Wiederanlauf im nächsten Lauf).
 *
 * Bestandsreferenzen des alten CalDAV-Publishs führen die Remote-ID
 * (= Objektname) im Payload-Key `object` und die UID in `external_id` —
 * beide Formate werden tolerant gelesen; Schreibvorgänge normalisieren aufs
 * Support-Format (external_id = Remote-ID, Payload hash+uid).
 *
 * WorkDiary bleibt führend; es werden nie externe Termine gelesen oder
 * überschrieben. Fehlgeschlagene Läufe zählen über
 * {@see RemoteCalendarConnection::recordConnectionFailure()} auf die
 * einheitliche Auto-Disable-Schwelle (MVP-178) ein.
 */
class RemoteCalendarPublishService {
    public const EXTERNAL_TYPE = 'calendar_event';

    /**
     * @param  list<RemoteCalendarItem>  $items
     * @return array{published: int, deleted: int, unchanged: int, failed: int}
     */
    public function publish(string $pluginId, RemoteCalendarConnection $connection, RemoteCalendarGateway $gateway, array $items, string $externalType = self::EXTERNAL_TYPE): array {
        $counters = ['published' => 0, 'deleted' => 0, 'unchanged' => 0, 'failed' => 0];

        foreach ($items as $item) {
            $ref = ExternalReference::query()
                ->forPlugin($connection->organizationId(), $pluginId, $externalType)
                ->where('referenceable_type', $item->referenceableType())
                ->where('referenceable_id', $item->referenceableId())
                ->first();

            if ($item->cancelled()) {
                if (! $ref instanceof ExternalReference) {
                    continue; // nie publiziert → nichts zu entfernen
                }
                if ($gateway->deleteEvent($this->remoteId($ref))) {
                    $ref->delete();
                    $counters['deleted']++;
                } else {
                    $counters['failed']++;
                }
                continue;
            }

            $hash = $item->fingerprint();
            if ($ref instanceof ExternalReference && ($ref->payload['hash'] ?? null) === $hash) {
                $counters['unchanged']++;
                continue;
            }

            if ($ref instanceof ExternalReference) {
                $remoteId = $this->remoteId($ref);
                if (! $gateway->updateEvent($remoteId, $item)) {
                    $counters['failed']++;
                    continue;
                }
                $ref->forceFill([
                    // normalisiert Alt-Referenzen (CalDAV: Objektname statt UID)
                    'external_id' => $remoteId,
                    'payload' => ['hash' => $hash, 'uid' => $item->uid()],
                    'synced_at' => Carbon::now(),
                ])->save();
                $counters['published']++;
                continue;
            }

            $remoteId = $gateway->createEvent($item);
            if ($remoteId === null) {
                $counters['failed']++;
                continue;
            }

            ExternalReference::query()->create([
                'organization_id' => $connection->organizationId(),
                'plugin_id' => $pluginId,
                'external_type' => $externalType,
                'referenceable_type' => $item->referenceableType(),
                'referenceable_id' => $item->referenceableId(),
                'external_id' => $remoteId,
                'payload' => ['hash' => $hash, 'uid' => $item->uid()],
                'synced_at' => Carbon::now(),
            ]);
            $counters['published']++;
        }

        $connection->markPublished();

        // Verbindungs-Gesundheit (MVP-178): fehlgeschlagene Requests zählen
        // als Störung (Auto-Disable-Schwelle), ein fehlerfreier Lauf setzt
        // den Zähler zurück.
        if ($counters['failed'] > 0) {
            $connection->recordConnectionFailure(
                sprintf('%d Kalender-Event(s) konnten nicht publiziert/entfernt werden.', $counters['failed']),
            );
        } else {
            $connection->recordConnectionSuccess();
        }

        return $counters;
    }

    /**
     * Remote-ID tolerant lesen: Alt-CalDAV-Referenzen führen den Objektnamen
     * im Payload (`object`), Support-Referenzen in `external_id`.
     */
    private function remoteId(ExternalReference $ref): string {
        return (string) (($ref->payload['object'] ?? null) ?: $ref->external_id);
    }
}
