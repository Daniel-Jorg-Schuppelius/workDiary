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

use APIToolkit\API\Pagination\{CursorPage, CursorPaginator};
use App\Models\Calendar\Event;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Plugins\GoogleCalendar\Api\GoogleCalendarClient;
use App\Plugins\GoogleCalendar\GoogleCalendarPlugin;
use App\Plugins\GoogleCalendar\Models\GoogleCalendarConnection;
use App\Plugins\Support\Calendar\{CalendarImportStager, RemoteCalendarPublishService};
use App\Services\CloudIntake\StaleCheckpointException;
use CommonToolkit\Entities\ICalendar\Document;
use CommonToolkit\Parsers\ICalendarParser;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\{Carbon, Collection};
use Throwable;

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
    private const MAX_PAGES = 500;

    public function __construct(private readonly CalendarImportStager $series) {}

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
        $items = new CursorPaginator(function (?string $pageToken) use ($client, &$syncToken, $windowStart, $windowEnd): CursorPage {
            try {
                $page = $client->eventsDelta($syncToken, $pageToken, $windowStart, $windowEnd);
            } catch (StaleCheckpointException) {
                // 410 Gone: genau EIN Vollabgleich ab Zeitfenster, danach
                // wieder inkrementell (Muster Cloud-Dokumenteingang).
                $syncToken = null;
                $page = $client->eventsDelta(null, null, $windowStart, $windowEnd);
            }
            if ($page['syncToken'] !== null) {
                $syncToken = $page['syncToken'];
            }

            return new CursorPage($page['items'], $page['pageToken']);
        }, maxPages: self::MAX_PAGES);

        /** @var array<string, mixed> $item */
        foreach ($items as $item) {
            $this->handleItem($connection, $item, $references, $counters, $windowStart->toDateTimeImmutable(), $windowEnd->toDateTimeImmutable());
        }

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
    private function handleItem(GoogleCalendarConnection $connection, array $item, Collection $references, array &$counters, DateTimeImmutable $windowStart, DateTimeImmutable $windowEnd): void {
        $remoteId = (string) ($item['id'] ?? '');
        if ($remoteId === '') {
            return;
        }
        $reference = $references->get($remoteId);
        $status = (string) ($item['status'] ?? 'confirmed');
        $subtitle = (string) ($connection->calendar_name ?? __('google_calendar::google_calendar.calendar.default'));

        // Abweichender oder abgesagter Einzeltermin einer Serie (MVP-977).
        $seriesId = $item['recurringEventId'] ?? null;
        if (is_string($seriesId) && $seriesId !== '') {
            $original = $this->instant($item['originalStartTime'] ?? null);
            if ($original === null) {
                return;
            }
            $key = CalendarImportStager::key($seriesId, $original);
            if ($status === 'cancelled') {
                $this->series->dismiss($connection->organization_id, GoogleCalendarPlugin::ID, $key);
            } elseif ($this->series->upsertOccurrence($connection->organization_id, GoogleCalendarPlugin::ID, $subtitle, $this->occurrence($item, $seriesId, $key))) {
                $counters['proposals']++;
            }

            return;
        }

        if ($status === 'cancelled') {
            // Nur publizierte Termine sind ein Handlungsfall; ein fremder
            // gelöschter Termin nimmt nur seine offenen Vorschläge mit.
            $this->series->dismissRemote($connection->organization_id, GoogleCalendarPlugin::ID, $remoteId);
            if ($reference instanceof ExternalReference && $this->stage(
                $connection,
                'calendar-deleted:' . $remoteId,
                IntegrationInboxItem::CASE_UNMATCHED,
                ['remote_id' => $remoteId],
                (string) __('google_calendar::google_calendar.import.deleted_title'),
                $reference,
            )) {
                $counters['deleted']++;
            }

            return;
        }

        if (! $reference instanceof ExternalReference && ($item['recurrence'] ?? []) !== []) {
            $counters['proposals'] += $this->series->stageSeries($connection->organization_id, GoogleCalendarPlugin::ID, $remoteId, $subtitle, $this->occurrences($item, $windowStart, $windowEnd));

            return;
        }

        if ($reference instanceof ExternalReference) {
            $updated = isset($item['updated']) ? Carbon::parse((string) $item['updated']) : null;
            if ($updated === null || CalendarImportStager::isOwnEcho($updated, $reference->synced_at)) {
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

        return $attributes;
    }

    /**
     * Vorkommen einer Serie im Fenster: Googles `recurrence` ist bereits
     * iCalendar (RRULE/EXDATE/RDATE), die Auflösung übernimmt das Toolkit.
     * Abweichende Einzeltermine kommen als eigene Einträge nach.
     *
     * @param  array<string, mixed>  $item
     * @return list<array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}>
     */
    private function occurrences(array $item, DateTimeImmutable $from, DateTimeImmutable $until): array {
        $start = $this->dateLine('DTSTART', $item['start'] ?? null);
        $end = $this->dateLine('DTEND', $item['end'] ?? null);
        if ($start === null) {
            return [];
        }
        $lines = array_filter((array) ($item['recurrence'] ?? []), static fn (mixed $line): bool => is_string($line) && preg_match('/^(RRULE|EXDATE|RDATE)[;:]/', $line) === 1);
        $ics = implode("\r\n", ['BEGIN:VCALENDAR', 'BEGIN:VEVENT', 'UID:' . $item['id'], $start, ...($end !== null ? [$end] : []), ...$lines, 'END:VEVENT', 'END:VCALENDAR']);
        try {
            $groups = ICalendarParser::fromString($ics)->getSeries();
            $instances = $groups === [] ? [] : Document::expand($groups[0], $from, $until, $this->zone(), CalendarImportStager::MAX_OCCURRENCES);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($instances as $instance) {
            $allDay = isset($item['start']['date']);
            $copy = $item;
            $copy['start'] = $allDay ? ['date' => $instance->getStart()->format('Y-m-d')] : ['dateTime' => $instance->getStart()->format(DATE_ATOM)];
            if ($instance->getEnd() !== null) {
                $copy['end'] = $allDay ? ['date' => $instance->getEnd()->format('Y-m-d')] : ['dateTime' => $instance->getEnd()->format(DATE_ATOM)];
            }
            $out[] = $this->occurrence($copy, (string) $item['id'], CalendarImportStager::key((string) $item['id'], $instance->getRecurrenceStart()));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}
     */
    private function occurrence(array $item, string $seriesId, string $key): array {
        $snapshot = ['series_uid' => $seriesId, 'series_title' => (string) ($item['summary'] ?? '')] + $this->snapshot($item);
        unset($snapshot['recurrence']);

        return ['key' => $key, 'title' => (string) ($item['summary'] ?? '—'), 'snapshot' => $snapshot, 'mapped' => $this->eventAttributes($item)];
    }

    /** DTSTART/DTEND-Zeile aus Googles `{dateTime, timeZone}` bzw. `{date}`. */
    private function dateLine(string $name, mixed $node): ?string {
        if (! is_array($node)) {
            return null;
        }
        if (is_string($node['date'] ?? null)) {
            return $name . ';VALUE=DATE:' . str_replace('-', '', $node['date']);
        }
        if (! is_string($node['dateTime'] ?? null)) {
            return null;
        }
        $zone = is_string($node['timeZone'] ?? null) && $node['timeZone'] !== '' ? (string) $node['timeZone'] : null;
        $moment = Carbon::parse($node['dateTime']);

        return $zone !== null
            ? $name . ';TZID=' . $zone . ':' . $moment->setTimezone($zone)->format('Ymd\THis')
            : $name . ':' . $moment->utc()->format('Ymd\THis\Z');
    }

    /** Ursprünglicher Beginn eines Einzeltermins als Zeitpunkt (Schlüssel des Vorkommens). */
    private function instant(mixed $node): ?DateTimeImmutable {
        if (! is_array($node)) {
            return null;
        }
        if (is_string($node['dateTime'] ?? null)) {
            return Carbon::parse($node['dateTime'])->toDateTimeImmutable();
        }
        if (is_string($node['date'] ?? null)) {
            return new DateTimeImmutable($node['date'] . ' 00:00:00', $this->zone());
        }

        return null;
    }

    /** Ganztägige Serien rechnen in der App-Zeitzone — wie die Übernahme ins Event. */
    private function zone(): DateTimeZone {
        return new DateTimeZone((string) config('app.timezone', 'Europe/Berlin'));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $mapped
     * @return bool true = NEUER Fall
     */
    private function stage(GoogleCalendarConnection $connection, string $dedupeKey, string $caseType, array $snapshot, string $title, ?ExternalReference $reference, ?array $mapped = null): bool {
        $subtitle = (string) ($connection->calendar_name ?? __('google_calendar::google_calendar.calendar.default'));

        return $this->series->stageCase($connection->organization_id, GoogleCalendarPlugin::ID, $subtitle, $dedupeKey, $caseType, $snapshot, $title, $reference, $mapped);
    }
}
