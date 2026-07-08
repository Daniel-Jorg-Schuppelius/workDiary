<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventCalendarSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

use App\Models\{Event, Organization};
use App\Plugins\CalDav\Contracts\CalendarSource;
use App\Services\Event\IcsFeedService;

/**
 * Liefert die zu publizierenden Kalenderelemente aus den {@see Event}s einer
 * Organisation (Feature 058, MVP-126). Fenster wie die Lese-Feeds (-30/+180
 * Tage); abgesagte Termine (`cancelled_at`) werden als `cancelled` markiert,
 * damit der Publisher sie extern entfernt. UID/ICS stammen aus dem
 * {@see IcsFeedService} — eine einzige Termin→ICS-Abbildung für Feed und CalDAV.
 */
class EventCalendarSource implements CalendarSource {
    public function __construct(private readonly IcsFeedService $ics) {}

    /**
     * @return list<CalendarPublishItem>
     */
    public function itemsFor(Organization $organization): array {
        $events = Event::query()
            ->where('organization_id', $organization->id)
            ->with('rooms')
            ->where('started_at', '>=', now()->subDays(30))
            ->where('started_at', '<=', now()->addDays(180))
            ->orderBy('started_at')
            ->get();

        $items = [];
        foreach ($events as $event) {
            $cancelled = $event->cancelled_at !== null;
            $items[] = new CalendarPublishItem(
                uid: IcsFeedService::eventUid($event),
                objectName: 'event-' . $event->getKey() . '.ics',
                ics: $cancelled ? '' : $this->ics->documentForEvent($event),
                referenceableType: $event->getMorphClass(),
                referenceableId: (int) $event->getKey(),
                cancelled: $cancelled,
            );
        }

        return $items;
    }
}
