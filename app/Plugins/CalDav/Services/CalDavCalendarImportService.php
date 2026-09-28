<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavCalendarImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Models\Calendar\Event;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Plugins\CalDav\CalDavConnection;
use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\CalDav\Contracts\CalDavGatewayFactory;
use App\Plugins\Support\Calendar\{CalendarSeriesStager, RemoteCalendarPublishService};
use CommonToolkit\Entities\ICalendar\{Document, Event as CalendarEvent};
use CommonToolkit\Parsers\ICalendarParser;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\{Carbon, Collection};
use Throwable;

/**
 * Kalender-Rückimport CalDAV (Feature 121, MVP-610b).
 *
 * CalDAV kennt weder Delta-Link noch Push: Der Abgleich läuft über
 * `sync-collection` (RFC 6578), wo der Server ihn kann, sonst über ein
 * rollierendes Zeitfenster mit ETag-Vergleich. Die Haltung bleibt die des
 * Microsoft- und Google-Zwillings: aus dem Kalender entstehen NUR
 * Integrations-Inbox-Fälle, nie blind angelegte Termine. Serien kommen als
 * Einzelvorkommen im Importfenster ({@see CalendarSeriesStager}, MVP-977).
 */
class CalDavCalendarImportService {
    /** Echo-Toleranz zwischen unserem PUT und dem LAST-MODIFIED des Servers. */
    private const ECHO_TOLERANCE_SECONDS = 120;

    public function __construct(
        private readonly CalDavGatewayFactory $gateways,
        private readonly CalendarSeriesStager $series,
    ) {}

    /** @return array{proposals: int, conflicts: int, deleted: int} */
    public function run(CalDavConnection $connection): array {
        $counters = ['proposals' => 0, 'conflicts' => 0, 'deleted' => 0];
        if (! $connection->two_way || ! $connection->active) {
            return $counters;
        }

        /** @var Collection<string, ExternalReference> $references */
        $references = ExternalReference::query()
            ->forPlugin($connection->organization_id, CalDavPlugin::ID, RemoteCalendarPublishService::EXTERNAL_TYPE)
            ->get()
            ->keyBy('external_id');

        $windowStart = Carbon::now()->subDays(30);
        $windowEnd = Carbon::now()->addDays(180);
        $page = $this->gateways->for($connection)->syncEvents(
            (string) ($connection->sync_token ?? ''),
            $this->localEtags($references),
            $windowStart,
            $windowEnd,
        );

        foreach ($page->changed as $change) {
            $this->handleChange($connection, $change, $references, $counters, $windowStart->toDateTimeImmutable(), $windowEnd->toDateTimeImmutable());
        }
        foreach ($page->deleted as $href) {
            $this->handleDeleted($connection, rawurldecode(basename($href)), $references, $counters);
        }

        $connection->forceFill([
            'sync_token' => $page->syncToken !== '' ? $page->syncToken : null,
            'last_imported_at' => Carbon::now(),
        ])->save();

        return $counters;
    }

    /**
     * Zuletzt gesehene ETags je Objektname. Sie liegen an der
     * Publish-Referenz — ein zweiter Speicherort würde nur auseinanderlaufen.
     *
     * @param  Collection<string, ExternalReference>  $references
     * @return array<string, string>
     */
    private function localEtags(Collection $references): array {
        $etags = [];
        foreach ($references as $externalId => $reference) {
            $etag = (string) (($reference->payload['etag'] ?? '') ?: '');
            if ($etag !== '') {
                $etags[(string) $externalId] = $etag;
            }
        }

        return $etags;
    }

    /**
     * @param  Collection<string, ExternalReference>  $references
     * @param  array{proposals: int, conflicts: int, deleted: int}  $counters
     */
    private function handleChange(CalDavConnection $connection, CalDavEventChange $change, Collection $references, array &$counters, DateTimeImmutable $windowStart, DateTimeImmutable $windowEnd): void {
        $parsed = $this->parse($change->ics);
        if ($parsed === null) {
            return; // unlesbares Objekt: nichts erfinden
        }

        $objectName = $change->objectName();
        $reference = $references->get($objectName);

        if (! $reference instanceof ExternalReference && $parsed['series'] !== null) {
            $counters['proposals'] += $this->series->stageSeries(
                $connection->organization_id,
                CalDavPlugin::ID,
                $parsed['series']['uid'],
                (string) $connection->name,
                $this->occurrences($parsed['series']['group'], $windowStart, $windowEnd),
            );

            return;
        }

        if ($reference instanceof ExternalReference) {
            // ETag am Beleg fortschreiben, damit der Fallback beim nächsten
            // Lauf ohne Neuladen vergleichen kann.
            $this->rememberEtag($reference, $change->etag);

            $modified = $parsed['last_modified'];
            $syncedAt = $reference->synced_at;
            if ($modified === null || $syncedAt === null
                || $modified->lessThanOrEqualTo($syncedAt->copy()->addSeconds(self::ECHO_TOLERANCE_SECONDS))) {
                return; // unser eigenes PUT
            }

            if ($this->stage(
                $connection,
                'calendar-conflict:' . $objectName . ':' . $modified->timestamp,
                IntegrationInboxItem::CASE_CONFLICT,
                $parsed['snapshot'],
                $parsed['snapshot']['subject'],
                $reference,
            )) {
                $counters['conflicts']++;
            }

            return;
        }

        if ($this->stage(
            $connection,
            'calendar-proposal:' . $objectName,
            IntegrationInboxItem::CASE_UNMATCHED,
            $parsed['snapshot'],
            $parsed['snapshot']['subject'],
            null,
            $parsed['attributes'],
        )) {
            $counters['proposals']++;
        }
    }

    /**
     * @param  Collection<string, ExternalReference>  $references
     * @param  array{proposals: int, conflicts: int, deleted: int}  $counters
     */
    private function handleDeleted(CalDavConnection $connection, string $objectName, Collection $references, array &$counters): void {
        $reference = $references->get($objectName);
        if (! $reference instanceof ExternalReference) {
            return; // fremdes Objekt: geht uns nichts an
        }

        if ($this->stage(
            $connection,
            'calendar-deleted:' . $objectName,
            IntegrationInboxItem::CASE_UNMATCHED,
            ['remote_id' => $objectName, 'subject' => (string) __('caldav.import.deleted_title')],
            (string) __('caldav.import.deleted_title'),
            $reference,
        )) {
            $counters['deleted']++;
        }
    }

    private function rememberEtag(ExternalReference $reference, string $etag): void {
        if ($etag === '' || (string) (($reference->payload['etag'] ?? '')) === $etag) {
            return;
        }
        $payload = (array) ($reference->payload ?? []);
        $payload['etag'] = $etag;
        $reference->forceFill(['payload' => $payload])->save();
    }

    /**
     * iCalendar lesen (Toolkit-Parser). Eine Serie (Master mit RRULE samt
     * abweichenden Einzelterminen derselben UID) kommt als Gruppe zurück.
     *
     * @return array{snapshot: array<string, mixed>, attributes: array<string, mixed>, last_modified: Carbon|null, series: array{uid: string, group: list<CalendarEvent>}|null}|null
     */
    private function parse(string $ics): ?array {
        try {
            $calendar = ICalendarParser::fromString($ics);
        } catch (Throwable) {
            return null;
        }

        $event = null;
        foreach ($calendar->getEvents() as $candidate) {
            if ($candidate->has('RECURRENCE-ID')) {
                continue;
            }
            $event = $candidate;

            break;
        }
        if ($event === null) {
            return null;
        }

        $mapped = $this->map($event);
        $modified = $event->getLastModified();
        $series = null;
        if ($event->has('RRULE') && $event->getUid() !== '') {
            foreach ($calendar->getSeries() as $group) {
                if ($group[0]->getUid() === $event->getUid()) {
                    $series = ['uid' => $event->getUid(), 'group' => $group];
                }
            }
        }

        return $mapped + ['last_modified' => $modified !== null ? Carbon::instance($modified) : null, 'series' => $series];
    }

    /**
     * Vorkommen der Serie im Fenster; abgesagte Einzeltermine entfallen.
     *
     * @param  list<CalendarEvent>  $group
     * @return list<array{key: string, title: string, snapshot: array<string, mixed>, mapped: array<string, mixed>}>
     */
    private function occurrences(array $group, DateTimeImmutable $from, DateTimeImmutable $until): array {
        $zone = new DateTimeZone(date_default_timezone_get());
        try {
            $instances = Document::expand($group, $from, $until, $zone, CalendarSeriesStager::MAX_OCCURRENCES);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($instances as $instance) {
            if (strtoupper((string) $instance->getEvent()->getText('STATUS')) === 'CANCELLED') {
                continue;
            }
            $mapped = $this->map($instance->getEvent(), $instance->getStart(), $instance->getEnd());
            $snapshot = $mapped['snapshot'] + ['series_uid' => $group[0]->getUid(), 'series_title' => $mapped['snapshot']['subject']];
            unset($snapshot['recurrence']);
            unset($mapped['attributes']['recurrence_rule']);
            $out[] = [
                'key' => CalendarSeriesStager::key($group[0]->getUid(), $instance->getRecurrenceStart()),
                'title' => (string) $snapshot['subject'],
                'snapshot' => $snapshot,
                'mapped' => $mapped['attributes'],
            ];
        }

        return $out;
    }

    /**
     * Snapshot und Event-Attribute eines Termins; ein Serienvorkommen übergibt
     * seine eigenen Zeiten.
     *
     * @return array{snapshot: array<string, mixed>, attributes: array<string, mixed>}
     */
    private function map(CalendarEvent $event, ?DateTimeImmutable $start = null, ?DateTimeImmutable $end = null): array {
        $timezone = (string) config('app.timezone', 'Europe/Berlin');
        $zone = new DateTimeZone(date_default_timezone_get());
        $start ??= $event->getStart($zone);
        $end ??= $event->getEnd($zone);
        $allDay = $event->isAllDay();
        $subject = $event->getSummary();
        $subject = $subject !== '' ? $subject : '—';

        $snapshot = [
            'remote_id' => $event->getUid(),
            'subject' => $subject,
            'start' => $start?->format(DATE_ATOM),
            'end' => $end?->format(DATE_ATOM),
            'location' => $event->getLocation(),
            'organizer' => $this->mailAddress($event->getProperty('ORGANIZER')?->getValue() ?? ''),
            'is_all_day' => $allDay,
            'recurrence' => $event->getProperty('RRULE')?->getValue(),
        ];

        $attributes = [
            'title' => $subject,
            'event_type' => 'meeting',
            'started_at' => $start === null ? null : Carbon::instance($start)->setTimezone($timezone)->format('Y-m-d H:i:s'),
            'ended_at' => $end === null ? null : Carbon::instance($end)->setTimezone($timezone)->format('Y-m-d H:i:s'),
            'is_all_day' => $allDay,
            'timezone' => $timezone,
        ];
        if ($snapshot['organizer'] !== null) {
            $attributes['external_contact_note'] = $snapshot['organizer'];
        }
        if ($event->has('RRULE')) {
            $attributes['recurrence_rule'] = (string) $event->getProperty('RRULE')?->getValue();
        }

        return ['snapshot' => $snapshot, 'attributes' => $attributes];
    }

    /** `mailto:`-Präfix des ORGANIZER abtrennen; ohne Adresse null. */
    private function mailAddress(string $value): ?string {
        $value = trim(preg_replace('/^mailto:/i', '', $value) ?? '');

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $mapped
     * @return bool true = NEUER Fall
     */
    private function stage(
        CalDavConnection $connection,
        string $dedupeKey,
        string $caseType,
        array $snapshot,
        string $title,
        ?ExternalReference $reference,
        ?array $mapped = null,
    ): bool {
        $item = IntegrationInboxItem::query()->firstOrCreate([
            'organization_id' => $connection->organization_id,
            'plugin_id' => CalDavPlugin::ID,
            'dedupe_key' => $dedupeKey,
        ], [
            'source' => CalDavPlugin::ID,
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
            'display_subtitle' => (string) $connection->name,
            'occurred_at' => now(),
        ]);

        return $item->wasRecentlyCreated;
    }
}
