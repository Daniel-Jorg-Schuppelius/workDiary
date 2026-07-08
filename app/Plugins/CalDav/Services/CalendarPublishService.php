<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarPublishService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Models\{CalDavConnection, ExternalReference};
use App\Plugins\CalDav\Contracts\CalDavGateway;
use Illuminate\Support\Carbon;

/**
 * Idempotenter Abgleich der Kalenderelemente gegen den externen CalDAV-Server
 * (Feature 058, MVP-126). Der Publish-Zustand liegt in
 * {@see ExternalReference} (Plugin `caldav`, Typ `calendar_object`, Payload-Hash
 * des ICS):
 *
 * - neu / geändert → PUT, Referenz mit neuem Hash;
 * - unverändert (Hash gleich) → übersprungen (kein PUT);
 * - abgesagt (`cancelled`) → DELETE, Referenz entfernt;
 * - Gateway-Fehler → `failed`, Referenz **unverändert** (Wiederanlauf im nächsten Lauf).
 *
 * WorkDiary bleibt führend; es werden nie externe Termine gelesen oder
 * überschrieben.
 */
class CalendarPublishService {
    public const PLUGIN_ID = 'caldav';

    public const EXTERNAL_TYPE = 'calendar_object';

    /**
     * @param  list<CalendarPublishItem>  $items
     * @return array{published: int, deleted: int, unchanged: int, failed: int}
     */
    public function publish(CalDavConnection $connection, CalDavGateway $gateway, array $items): array {
        $counters = ['published' => 0, 'deleted' => 0, 'unchanged' => 0, 'failed' => 0];

        foreach ($items as $item) {
            $ref = ExternalReference::query()
                ->where('organization_id', $connection->organization_id)
                ->where('plugin_id', self::PLUGIN_ID)
                ->where('external_type', self::EXTERNAL_TYPE)
                ->where('referenceable_type', $item->referenceableType)
                ->where('referenceable_id', $item->referenceableId)
                ->first();

            if ($item->cancelled) {
                if (! $ref instanceof ExternalReference) {
                    continue; // nie publiziert → nichts zu entfernen
                }
                if ($gateway->deleteObject($item->objectName)) {
                    $ref->delete();
                    $counters['deleted']++;
                } else {
                    $counters['failed']++;
                }
                continue;
            }

            $hash = hash('sha256', $item->ics);
            if ($ref instanceof ExternalReference && ($ref->payload['hash'] ?? null) === $hash) {
                $counters['unchanged']++;
                continue;
            }

            if (! $gateway->putObject($item->objectName, $item->ics)) {
                $counters['failed']++;
                continue;
            }

            ExternalReference::query()->updateOrCreate(
                [
                    'plugin_id' => self::PLUGIN_ID,
                    'external_type' => self::EXTERNAL_TYPE,
                    'referenceable_type' => $item->referenceableType,
                    'referenceable_id' => $item->referenceableId,
                ],
                [
                    'organization_id' => $connection->organization_id,
                    'external_id' => $item->uid,
                    'payload' => ['hash' => $hash, 'object' => $item->objectName],
                    'synced_at' => Carbon::now(),
                ],
            );
            $counters['published']++;
        }

        $connection->forceFill(['last_published_at' => Carbon::now()])->save();

        return $counters;
    }
}
