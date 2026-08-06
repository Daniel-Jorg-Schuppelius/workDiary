<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphCalendarImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Models\{ExternalReference, IntegrationInboxItem, MsgraphConnection};
use App\Plugins\Msgraph\Api\MsgraphCalendarClient;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use App\Services\CloudIntake\StaleCheckpointException;
use Illuminate\Support\Carbon;

/**
 * Zwei-Wege-Kalender, erster Schnitt (Feature 102, C3 — das in Bauturbo A8
 * verschobene Epic): calendarView-DELTA des Ziel-Kalenders → NUR
 * Integrations-Inbox-Fälle, nie blinde Event-Anlage (Leitsatz 056/080):
 *
 * - Externer Termin OHNE eigene Referenz ⇒ Vorschlag (`calendar-proposal`).
 * - PUBLIZIERTER Termin remote geändert ⇒ Konflikt (`calendar-conflict`) —
 *   der nächste Publish-Lauf würde die Änderung sonst still überschreiben.
 *   Publish-Echos werden über `lastModifiedDateTime` ≤ `synced_at`+Toleranz
 *   herausgefiltert (unsere eigenen PATCHes erscheinen auch im Delta).
 * - PUBLIZIERTER Termin remote gelöscht (@removed) ⇒ Hinweis
 *   (`calendar-deleted`) — der nächste Publish legt ihn sonst kommentarlos
 *   neu an.
 *
 * Serien: nur `singleInstance`/`seriesMaster` als Vorschlag; `occurrence`/
 * `exception` bewusst übersprungen (Serien-Auflösung ist Folgeausbau).
 * Checkpoint = absolute Delta-URL an der Verbindung; 410 ⇒ Neustart ab
 * Zeitfenster (−30/+180 Tage, wie der Publish).
 */
class MsgraphCalendarImportService {
    /** Publish-Echo-Toleranz zwischen unserem PATCH und Graphs lastModified. */
    private const ECHO_TOLERANCE_SECONDS = 120;

    /** @return array{proposals: int, conflicts: int, deleted: int} */
    public function run(MsgraphConnection $connection): array {
        $counters = ['proposals' => 0, 'conflicts' => 0, 'deleted' => 0];
        if (! $connection->two_way || ! $connection->isActive()) {
            return $counters;
        }

        $client = new MsgraphCalendarClient($connection);
        $windowStart = Carbon::now()->subDays(30);
        $windowEnd = Carbon::now()->addDays(180);

        /** @var \Illuminate\Support\Collection<string, ExternalReference> $references */
        $references = ExternalReference::query()
            ->forPlugin($connection->organization_id, MsgraphPlugin::ID, RemoteCalendarPublishService::EXTERNAL_TYPE)
            ->get()
            ->keyBy('external_id');

        $checkpoint = $connection->calendar_delta_link;
        do {
            try {
                $page = $client->calendarDelta($checkpoint, $windowStart, $windowEnd);
            } catch (StaleCheckpointException) {
                // Abgelaufenes Token: einmal ab Fenster neu aufsetzen.
                $page = $client->calendarDelta(null, $windowStart, $windowEnd);
            }

            foreach ($page['items'] as $item) {
                $this->handleItem($connection, $item, $references, $counters);
            }

            $checkpoint = $page['checkpoint'];
        } while ($page['hasMore']);

        $connection->forceFill([
            'calendar_delta_link' => $checkpoint !== '' ? $checkpoint : null,
            'last_imported_at' => Carbon::now(),
        ])->save();

        return $counters;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  \Illuminate\Support\Collection<string, ExternalReference>  $references
     * @param  array{proposals: int, conflicts: int, deleted: int}  $counters
     */
    private function handleItem(MsgraphConnection $connection, array $item, $references, array &$counters): void {
        $remoteId = (string) ($item['id'] ?? '');
        if ($remoteId === '') {
            return;
        }
        $reference = $references->get($remoteId);

        // @removed: nur publizierte Termine sind ein Handlungsfall.
        if (isset($item['@removed'])) {
            if ($reference instanceof ExternalReference && $this->stage($connection, 'calendar-deleted:' . $remoteId, IntegrationInboxItem::CASE_UNMATCHED, [
                'remote_id' => $remoteId,
            ], (string) __('msgraph.import.deleted_title'), $reference)) {
                $counters['deleted']++;
            }

            return;
        }

        $type = (string) ($item['type'] ?? 'singleInstance');
        if (! in_array($type, ['singleInstance', 'seriesMaster'], true)) {
            return; // occurrences/exceptions: Serien-Auflösung ist Folgeausbau
        }

        if ($reference instanceof ExternalReference) {
            // Publish-Echo? Unsere eigenen Create/PATCHes tauchen im Delta auf.
            $lastModified = isset($item['lastModifiedDateTime']) ? Carbon::parse((string) $item['lastModifiedDateTime']) : null;
            $syncedAt = $reference->synced_at;
            if ($lastModified === null || $syncedAt === null
                || $lastModified->lessThanOrEqualTo($syncedAt->copy()->addSeconds(self::ECHO_TOLERANCE_SECONDS))) {
                return; // von uns selbst — kein externer Eingriff
            }

            if ($this->stage($connection, 'calendar-conflict:' . $remoteId . ':' . $lastModified->timestamp, IntegrationInboxItem::CASE_CONFLICT, $this->snapshot($item), (string) ($item['subject'] ?? '—'), $reference)) {
                $counters['conflicts']++;
            }

            return;
        }

        // Externer Termin ohne Referenz → Vorschlag (nie blind anlegen).
        if ($this->stage($connection, 'calendar-proposal:' . $remoteId, IntegrationInboxItem::CASE_UNMATCHED, $this->snapshot($item), (string) ($item['subject'] ?? '—'), null)) {
            $counters['proposals']++;
        }
    }

    /**
     * Kompakter Termin-Snapshot für die Inbox (keine Anhänge/Body-Volltexte).
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function snapshot(array $item): array {
        return [
            'remote_id' => (string) ($item['id'] ?? ''),
            'subject' => (string) ($item['subject'] ?? ''),
            'start' => $item['start']['dateTime'] ?? null,
            'end' => $item['end']['dateTime'] ?? null,
            'timezone' => $item['start']['timeZone'] ?? null,
            'location' => $item['location']['displayName'] ?? null,
            'organizer' => $item['organizer']['emailAddress']['address'] ?? null,
            'is_all_day' => (bool) ($item['isAllDay'] ?? false),
            'type' => (string) ($item['type'] ?? 'singleInstance'),
        ];
    }

    /**
     * Inbox-Fall deduplizieren + anlegen.
     *
     * @param  array<string, mixed>  $snapshot
     * @return bool true = NEUER Fall
     */
    private function stage(MsgraphConnection $connection, string $dedupeKey, string $caseType, array $snapshot, string $title, ?ExternalReference $reference): bool {
        $item = IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $connection->organization_id,
            'plugin_id' => MsgraphPlugin::ID,
            'dedupe_key' => $dedupeKey,
        ], [
            'source' => MsgraphPlugin::ID,
            'target_type' => (new \App\Models\Event())->getMorphClass(),
            'external_type' => RemoteCalendarPublishService::EXTERNAL_TYPE,
            'external_id' => (string) ($snapshot['remote_id'] ?? ''),
            'case_type' => $caseType,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $reference?->referenceable_type,
            'referenceable_id' => $reference?->referenceable_id,
            'remote_snapshot' => $snapshot,
            'display_title' => $title !== '' ? $title : '—',
            'display_subtitle' => (string) ($connection->calendar_name ?? __('msgraph.calendar.default')),
            'occurred_at' => now(),
        ]);

        return $item->wasRecentlyCreated;
    }
}
