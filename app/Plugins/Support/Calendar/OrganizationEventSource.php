<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationEventSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

use App\Models\{Event, Organization};
use App\Services\Event\IcsFeedService;
use DateTimeZone;

/**
 * Liefert die zu publizierenden Kalenderelemente aus den {@see Event}s einer
 * Organisation für REST-Kalender-Provider (MVP-328, Bauturbo A8). Fenster und
 * Absage-Semantik wie die CalDAV-Quelle
 * ({@see \App\Plugins\CalDav\Services\EventCalendarSource}: -30/+180 Tage,
 * `cancelled_at` ⇒ extern entfernen); die UID stammt aus
 * {@see IcsFeedService::eventUid()} — Feed, CalDAV und REST-Provider meinen
 * denselben Termin. Zeiten in der lokalen App-Zeitzone (wie die ICS-Abbildung).
 */
class OrganizationEventSource {
    /**
     * @return list<RemoteCalendarEvent>
     */
    public function itemsFor(Organization $organization): array {
        $tz = new DateTimeZone((string) config('app.timezone', 'Europe/Berlin'));

        $events = Event::query()
            ->where('organization_id', $organization->id)
            ->with('rooms')
            ->where('started_at', '>=', now()->subDays(30))
            ->where('started_at', '<=', now()->addDays(180))
            ->orderBy('started_at')
            ->get();

        $items = [];
        foreach ($events as $event) {
            $location = $event->rooms
                ->map(fn($r) => trim($r->building . ' ' . $r->name))
                ->filter()
                ->implode(', ');

            $items[] = new RemoteCalendarEvent(
                uid: IcsFeedService::eventUid($event),
                title: (string) $event->title,
                description: $event->description !== null && $event->description !== '' ? (string) $event->description : null,
                location: $location,
                start: $event->started_at->copy()->setTimezone($tz)->toDateTimeImmutable(),
                end: $event->ended_at->copy()->setTimezone($tz)->toDateTimeImmutable(),
                timezone: $tz->getName(),
                referenceableType: $event->getMorphClass(),
                referenceableId: (int) $event->getKey(),
                cancelled: $event->cancelled_at !== null,
            );
        }

        return $items;
    }
}
