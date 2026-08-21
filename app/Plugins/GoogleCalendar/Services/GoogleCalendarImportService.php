<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\GoogleCalendar\Services;

use App\Models\{Event, ExternalReference, GoogleCalendarConnection, IntegrationInboxItem};
use App\Plugins\GoogleCalendar\Api\GoogleCalendarClient;
use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use App\Services\CloudIntake\StaleCheckpointException;
use Illuminate\Support\{Carbon, Collection};

/**
 * Kalender-Rückimport Google (Feature 121, MVP-610a).
 *
 * Gleiche Haltung wie beim Microsoft-Zwilling: aus dem Kalender entstehen NUR
 * Integrations-Inbox-Fälle, nie blind angelegte Termine.
 *
 * - Externer Termin ohne eigene Referenz ⇒ Vorschlag (`calendar-proposal`).
 * - Publizierter Termin remote geändert ⇒ Konflikt (`calendar-conflict`);
 *   der nächste Publish-Lauf würde die Änderung sonst still überschreiben.
 * - Publizierter Termin remote gelöscht/abgesagt ⇒ Hinweis (`calendar-deleted`).
 *
 * Publish-Echos filtert `updated` ≤ `synced_at` + Toleranz heraus: unsere
 * eigenen PUTs erscheinen in der Änderungsliste ebenfalls.
 */
class GoogleCalendarImportService {
    /** Echo-Toleranz zwischen unserem PUT und Googles `updated`. */
    private const ECHO_TOLERANCE_SECONDS = 120;

    /** @return array{proposals: int, conflicts: int, deleted: int} */
    public function run(GoogleCalendarConnection $connection): array {
        $counters = ['proposals' => 0, 'conflicts' => 0, 'deleted' => 0];
        if (! $connection->two_way || ! $connection->isActive()) {
            return $counters;
        }

        $client = new GoogleCalendarClient($connection);
        $windowStart = Carbon::now()->subDays(30);
        $windowEnd = Carbon::now()->addDays(180);

        /** @var Collection<string, ExternalReference> $references */
        $references = ExternalReference::query()
            ->forPlugin($connection->organization_id, GoogleCalendarPlugin::ID, RemoteCalendarPublishService::EXTERNAL_TYPE)
            ->get()
            ->keyBy('external_id');

        $syncToken = $connection->sync_token;
        $pageToken = null;
        do {
            try {
                $page = $client->eventsDelta($syncToken, $pageToken, $windowStart, $windowEnd);
            } catch (StaleCheckpointException) {
                // 410 Gone: genau EIN Vollabgleich ab Zeitfenster, danach
                // wieder inkrementell (Muster Cloud-Dokumenteingang).
                $syncToken = null;
                $pageToken = null;
                $page = $client->eventsDelta(null, null, $windowStart, $windowEnd);
            }

            foreach ($page['items'] as $item) {
                $this->handleItem($connection, $item, $references, $counters);
            }

            $pageToken = $page['pageToken'];
            if ($page['syncToken'] !== null) {
                $syncToken = $page['syncToken'];
            }
        } while ($pageToken !== null);

        $connection->forceFill([
            'sync_token' => $syncToken,
            'last_imported_at' => Carbon::now(),
        ])->save();

        return $counters;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<string, ExternalReference>  $references
     * @param  array{proposals: int, conflicts: int, deleted: int}  $counters
     */
    private function handleItem(GoogleCalendarConnection $connection, array $item, Collection $references, array &$counters): void {
        $remoteId = (string) ($item['id'] ?? '');
        if ($remoteId === '') {
            return;
        }
        $reference = $references->get($remoteId);
        $status = (string) ($item['status'] ?? 'confirmed');

        if ($status === 'cancelled') {
            // Nur publizierte Termine sind ein Handlungsfall — ein fremder
            // gelöschter Termin geht uns nichts an.
            if ($reference instanceof ExternalReference && $this->stage(
                $connection,
                'calendar-deleted:' . $remoteId,
                IntegrationInboxItem::CASE_UNMATCHED,
                ['remote_id' => $remoteId],
                (string) __('google_calendar.import.deleted_title'),
                $reference,
            )) {
                $counters['deleted']++;
            }

            return;
        }

        // Serien: Master und Einzeltermine ja, abgeleitete Instanzen nein
        // (Serien-Auflösung ist Folgeausbau — wie beim Microsoft-Zwilling).
        if (($item['recurringEventId'] ?? null) !== null) {
            return;
        }

        if ($reference instanceof ExternalReference) {
            $updated = isset($item['updated']) ? Carbon::parse((string) $item['updated']) : null;
            $syncedAt = $reference->synced_at;
            if ($updated === null || $syncedAt === null
                || $updated->lessThanOrEqualTo($syncedAt->copy()->addSeconds(self::ECHO_TOLERANCE_SECONDS))) {
                return; // von uns selbst
            }

            if ($this->stage(
                $connection,
                'calendar-conflict:' . $remoteId . ':' . $updated->timestamp,
                IntegrationInboxItem::CASE_CONFLICT,
                $this->snapshot($item),
                (string) ($item['summary'] ?? '—'),
                $reference,
            )) {
                $counters['conflicts']++;
            }

            return;
        }

        if ($this->stage(
            $connection,
            'calendar-proposal:' . $remoteId,
            IntegrationInboxItem::CASE_UNMATCHED,
            $this->snapshot($item),
            (string) ($item['summary'] ?? '—'),
            null,
            $this->eventAttributes($item),
        )) {
            $counters['proposals']++;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function snapshot(array $item): array {
        return [
            'remote_id' => (string) ($item['id'] ?? ''),
            'subject' => (string) ($item['summary'] ?? ''),
            'start' => $item['start']['dateTime'] ?? ($item['start']['date'] ?? null),
            'end' => $item['end']['dateTime'] ?? ($item['end']['date'] ?? null),
            'timezone' => $item['start']['timeZone'] ?? null,
            'location' => $item['location'] ?? null,
            'organizer' => $item['organizer']['email'] ?? null,
            'is_all_day' => isset($item['start']['date']),
            'recurrence' => $item['recurrence'][0] ?? null,
        ];
    }

    /**
     * Event-Attribute für „Neu anlegen" aus der Inbox. Zeiten in die
     * App-Zeitzone; Googles RRULE-Zeilen sind bereits iCalendar-Format und
     * werden ohne den `RRULE:`-Präfix übernommen.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function eventAttributes(array $item): array {
        $timezone = (string) config('app.timezone', 'Europe/Berlin');
        $allDay = isset($item['start']['date']);
        $parse = static function (mixed $node) use ($timezone, $allDay): ?string {
            if (! is_array($node)) {
                return null;
            }
            $value = $node['dateTime'] ?? $node['date'] ?? null;
            if (! is_string($value) || $value === '') {
                return null;
            }

            return Carbon::parse($value, (string) ($node['timeZone'] ?? ($allDay ? $timezone : 'UTC')))
                ->setTimezone($timezone)
                ->format('Y-m-d H:i:s');
        };

        $attributes = [
            'title' => (string) (($item['summary'] ?? '') !== '' ? $item['summary'] : '—'),
            'event_type' => 'meeting',
            'started_at' => $parse($item['start'] ?? null),
            'ended_at' => $parse($item['end'] ?? null),
            'is_all_day' => $allDay,
            'timezone' => $timezone,
        ];

        $organizer = $item['organizer']['email'] ?? null;
        if (is_string($organizer) && $organizer !== '') {
            $attributes['external_contact_note'] = $organizer;
        }

        $rule = $this->recurrenceRule($item);
        if ($rule !== null) {
            $attributes['recurrence_rule'] = $rule;
        }

        return $attributes;
    }

    /**
     * Googles `recurrence` ist bereits iCalendar — nur der Präfix fällt weg.
     *
     * @param  array<string, mixed>  $item
     */
    private function recurrenceRule(array $item): ?string {
        foreach ((array) ($item['recurrence'] ?? []) as $line) {
            if (is_string($line) && str_starts_with($line, 'RRULE:')) {
                return substr($line, 6);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $mapped
     * @return bool true = NEUER Fall
     */
    private function stage(
        GoogleCalendarConnection $connection,
        string $dedupeKey,
        string $caseType,
        array $snapshot,
        string $title,
        ?ExternalReference $reference,
        ?array $mapped = null,
    ): bool {
        $item = IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $connection->organization_id,
            'plugin_id' => GoogleCalendarPlugin::ID,
            'dedupe_key' => $dedupeKey,
        ], [
            'source' => GoogleCalendarPlugin::ID,
            'target_type' => (new Event)->getMorphClass(),
            'external_type' => RemoteCalendarPublishService::EXTERNAL_TYPE,
            'external_id' => (string) ($snapshot['remote_id'] ?? ''),
            'case_type' => $caseType,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'referenceable_type' => $reference?->referenceable_type,
            'referenceable_id' => $reference?->referenceable_id,
            'remote_snapshot' => $snapshot,
            'mapped_snapshot' => $mapped,
            'display_title' => $title !== '' ? $title : '—',
            'display_subtitle' => (string) ($connection->calendar_name ?? __('google_calendar.calendar.default')),
            'occurred_at' => now(),
        ]);

        return $item->wasRecentlyCreated;
    }
}
