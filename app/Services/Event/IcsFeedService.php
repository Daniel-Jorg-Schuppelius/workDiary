<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcsFeedService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Event;

use App\Enums\Event\EventVisibility;
use App\Models\Event;
use App\Models\User;
use DateTimeZone;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event as IcsEvent;
use Spatie\IcalendarGenerator\Enums\Classification;

/**
 * Generiert ICS-Feeds für Events. Berücksichtigt Sichtbarkeit (interne
 * Events nur für Mitarbeitende, externe für Mandant/Kunden-Sicht etc.)
 * und filtert auf einen sinnvollen Zeitraum (-30/+180 Tage).
 */
class IcsFeedService {
    public function feedForUser(User $user): string {
        $events = Event::query()
            ->with(['rooms', 'category'])
            ->whereNull('cancelled_at')
            ->forUser($user)
            ->where('started_at', '>=', now()->subDays(30))
            ->where('started_at', '<=', now()->addDays(180))
            ->orderBy('started_at')
            ->get();

        return $this->build($events->all(), 'workDiary – Persönlicher Kalender');
    }

    public function feedPublic(): string {
        $events = Event::query()
            ->with(['rooms', 'category'])
            ->whereNull('cancelled_at')
            ->where('visibility', EventVisibility::Public->value)
            ->where('started_at', '>=', now()->subDays(30))
            ->where('started_at', '<=', now()->addDays(365))
            ->orderBy('started_at')
            ->get();

        return $this->build($events->all(), 'workDiary – Öffentliche Veranstaltungen');
    }

    /**
     * @param array<int, Event> $events
     */
    private function build(array $events, string $name): string {
        $calendar = Calendar::create($name)
            ->productIdentifier((string) config('events.ics.product_id', '-//workDiary//Events//DE'))
            ->refreshInterval(60);

        $tz = new DateTimeZone((string) config('app.timezone', 'Europe/Berlin'));

        foreach ($events as $event) {
            $start = $event->started_at->copy()->setTimezone($tz);
            $end = $event->ended_at->copy()->setTimezone($tz);

            $location = $event->rooms
                ->map(fn($r) => trim($r->building . ' ' . $r->name))
                ->filter()
                ->implode(', ');

            $ics = IcsEvent::create($event->title)
                ->uniqueIdentifier('event-' . $event->getKey() . '@workdiary')
                ->startsAt($start->toDateTimeImmutable())
                ->endsAt($end->toDateTimeImmutable())
                ->withoutTimezone(); // Zeiten in lokaler TZ persistiert

            if (! empty($event->description)) {
                $ics->description($event->description);
            }
            if ($location !== '') {
                $ics->address($location);
            }
            $ics->classification(match ($event->visibility) {
                EventVisibility::Public => Classification::Public,
                EventVisibility::External => Classification::Public,
                EventVisibility::Internal => Classification::Private,
            });

            $calendar->event($ics);
        }

        return $calendar->get();
    }
}
