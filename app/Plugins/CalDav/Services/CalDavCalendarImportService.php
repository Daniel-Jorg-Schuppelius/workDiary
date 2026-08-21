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

use App\Models\{CalDavConnection, Event, ExternalReference, IntegrationInboxItem};
use App\Plugins\CalDav\CalDavPlugin;
use App\Plugins\CalDav\Contracts\CalDavGatewayFactory;
use App\Plugins\Support\Calendar\RemoteCalendarPublishService;
use Illuminate\Support\{Carbon, Collection};
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Throwable;

/**
 * Kalender-Rückimport CalDAV (Feature 121, MVP-610b).
 *
 * CalDAV kennt weder Delta-Link noch Push: Der Abgleich läuft über
 * `sync-collection` (RFC 6578), wo der Server ihn kann, sonst über ein
 * rollierendes Zeitfenster mit ETag-Vergleich. Die Haltung bleibt die des
 * Microsoft- und Google-Zwillings: aus dem Kalender entstehen NUR
 * Integrations-Inbox-Fälle, nie blind angelegte Termine.
 */
class CalDavCalendarImportService {
    /** Echo-Toleranz zwischen unserem PUT und dem LAST-MODIFIED des Servers. */
    private const ECHO_TOLERANCE_SECONDS = 120;

    public function __construct(private readonly CalDavGatewayFactory $gateways) {}

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

        $page = $this->gateways->for($connection)->syncEvents(
            (string) ($connection->sync_token ?? ''),
            $this->localEtags($references),
            Carbon::now()->subDays(30),
            Carbon::now()->addDays(180),
        );

        foreach ($page->changed as $change) {
            $this->handleChange($connection, $change, $references, $counters);
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
    private function handleChange(CalDavConnection $connection, CalDavEventChange $change, Collection $references, array &$counters): void {
        $parsed = $this->parse($change->ics);
        if ($parsed === null) {
            return; // unlesbares Objekt: nichts erfinden
        }

        $objectName = $change->objectName();
        $reference = $references->get($objectName);

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
     * iCalendar lesen (sabre/vobject — dieselbe Bibliothek, die den Publish
     * schreibt). Serieninstanzen mit RECURRENCE-ID bleiben draußen; die
     * Serien-Auflösung ist Folgeausbau wie bei den anderen Anbietern.
     *
     * @return array{snapshot: array<string, mixed>, attributes: array<string, mixed>, last_modified: Carbon|null}|null
     */
    private function parse(string $ics): ?array {
        try {
            $calendar = Reader::read($ics, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return null;
        }
        if (! $calendar instanceof VCalendar) {
            return null;
        }

        $event = null;
        foreach ($calendar->VEVENT ?? [] as $candidate) {
            if (isset($candidate->{'RECURRENCE-ID'})) {
                continue;
            }
            $event = $candidate;

            break;
        }
        if ($event === null) {
            return null;
        }

        $timezone = (string) config('app.timezone', 'Europe/Berlin');
        $start = isset($event->DTSTART) ? $event->DTSTART->getDateTime() : null;
        $end = isset($event->DTEND) ? $event->DTEND->getDateTime() : null;
        $allDay = isset($event->DTSTART) && ! $event->DTSTART->hasTime();
        $subject = trim((string) ($event->SUMMARY ?? ''));
        $subject = $subject !== '' ? $subject : '—';

        $snapshot = [
            'remote_id' => trim((string) ($event->UID ?? '')),
            'subject' => $subject,
            'start' => $start?->format(DATE_ATOM),
            'end' => $end?->format(DATE_ATOM),
            'location' => trim((string) ($event->LOCATION ?? '')) ?: null,
            'organizer' => $this->mailAddress((string) ($event->ORGANIZER ?? '')),
            'is_all_day' => $allDay,
            'recurrence' => isset($event->RRULE) ? (string) $event->RRULE : null,
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
        if (isset($event->RRULE)) {
            $attributes['recurrence_rule'] = (string) $event->RRULE;
        }

        $lastModified = null;
        foreach (['LAST-MODIFIED', 'DTSTAMP'] as $property) {
            if (isset($event->{$property})) {
                $lastModified = Carbon::parse((string) $event->{$property});

                break;
            }
        }

        return ['snapshot' => $snapshot, 'attributes' => $attributes, 'last_modified' => $lastModified];
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
