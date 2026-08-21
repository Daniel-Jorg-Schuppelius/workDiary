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
use App\Enums\Vacation\VacationStatus;
use App\Models\{AppointmentRequest, Event, ScheduledShift, User, Vacation};
use Carbon\CarbonImmutable;
use DateTimeZone;
use Spatie\IcalendarGenerator\Components\{Calendar, Event as IcsEvent};
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
        // Bewusst org-agnostisch: liefert ausschließlich Events mit
        // Visibility=Public über alle Organisationen hinweg (opt-in durch den
        // jeweiligen Mandanten). Siehe PublicRouteTenantTest.
        $events = Event::query()
            ->withoutGlobalScopes()
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
     * Persönlicher Schedule-Feed: genehmigte Urlaube + veröffentlichte
     * Schichten des Users. Wird via Token öffentlich abgerufen, damit
     * externe Kalender (Google/Outlook/Apple) per URL-Subscribe synchronisieren.
     */
    public function feedPersonalSchedule(User $user): string {
        $tz = new DateTimeZone((string) config('app.timezone', 'Europe/Berlin'));

        $calendar = Calendar::create('workDiary – ' . $user->name)
            ->productIdentifier((string) config('events.ics.product_id', '-//workDiary//Schedule//DE'))
            ->refreshInterval(60);

        $vacations = Vacation::query()
            ->where('user_id', $user->id)
            ->where('status', VacationStatus::Approved->value)
            ->where('end_date', '>=', CarbonImmutable::now()->subYear()->toDateString())
            ->orderBy('start_date')
            ->get();

        foreach ($vacations as $v) {
            // DTEND ist bei VALUE=DATE exklusiv → +1 Tag (intern via fullDay())
            $vac = IcsEvent::create(__('Urlaub: :name', ['name' => $user->name]))
                ->uniqueIdentifier('vacation-' . $v->id . '@workdiary')
                ->fullDay()
                ->startsAt($v->start_date->copy()->toDateTimeImmutable())
                ->endsAt($v->end_date->copy()->addDay()->toDateTimeImmutable());

            $calendar->event($vac);
        }

        $shifts = ScheduledShift::query()
            ->with('shiftType')
            ->where('user_id', $user->id)
            ->where('date', '>=', CarbonImmutable::now()->subMonths(2)->toDateString())
            ->orderBy('date')
            ->get();

        foreach ($shifts as $s) {
            if (! $s->start_time || ! $s->end_time) {
                continue;
            }
            $date = $s->date->format('Y-m-d');
            $start = CarbonImmutable::parse($date . ' ' . $s->start_time, $tz);
            $end = CarbonImmutable::parse($date . ' ' . $s->end_time, $tz);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            $label = $s->shiftType->name ?? __('Schicht');
            $ics = IcsEvent::create((string) $label)
                ->uniqueIdentifier('shift-' . $s->id . '@workdiary')
                ->startsAt($start->toDateTimeImmutable())
                ->endsAt($end->toDateTimeImmutable())
                ->withoutTimezone();

            $calendar->event($ics);
        }

        return $calendar->get();
    }

    /**
     * Stabile CalDAV-/ICS-UID eines Events (Feature 058) — identisch zu den
     * Feed-UIDs, damit Feed-Abo und CalDAV-Publish denselben Termin meinen.
     */
    public static function eventUid(Event $event): string {
        return 'event-' . $event->getKey() . '@workdiary';
    }

    /**
     * Einzel-Event-ICS-Dokument (VCALENDAR mit genau einem VEVENT) für das
     * idempotente CalDAV-Publish (Feature 058, MVP-126). Nutzt dieselbe TZ-/
     * UID-/Sichtbarkeits-Abbildung wie die Lese-Feeds.
     */
    public function documentForEvent(Event $event): string {
        $tz = new DateTimeZone((string) config('app.timezone', 'Europe/Berlin'));

        return Calendar::create($event->title)
            ->productIdentifier((string) config('events.ics.product_id', '-//workDiary//Events//DE'))
            ->event($this->toIcsEvent($event, $tz))
            ->get();
    }

    /**
     * Stabile ICS-UID eines Terminwunsches (Feature 095) — für die
     * Invitee-Bestätigung eines bestätigten Calendly-Termins.
     */
    public static function appointmentUid(AppointmentRequest $appointment): string {
        return 'appointment-' . $appointment->sqid . '@workdiary';
    }

    /**
     * Einzel-Termin-ICS-Dokument (VCALENDAR mit genau einem VEVENT) für die
     * Invitee-Bestätigung eines bestätigten Calendly-Terminwunsches (Feature 095).
     */
    public function documentForAppointment(AppointmentRequest $appointment): string {
        $start = $appointment->start_at?->copy() ?? CarbonImmutable::now();
        $end = $appointment->end_at?->copy() ?? $start->copy()->addMinutes(30);
        $title = (string) ($appointment->service_label ?? __('Termin'));

        $event = IcsEvent::create($title)
            ->uniqueIdentifier(self::appointmentUid($appointment))
            ->startsAt($start->toDateTimeImmutable())
            ->endsAt($end->toDateTimeImmutable());

        return Calendar::create($title)
            ->productIdentifier((string) config('events.ics.product_id', '-//workDiary//Appointments//DE'))
            ->event($event)
            ->get();
    }

    /**
     * Stabile CalDAV-/ICS-UID einer Schicht bzw. eines Urlaubs (Feature 058,
     * Rang 17). Sqid-basiert (opake ID, verrät der externen Collection keine
     * laufenden Zähler).
     */
    public static function shiftUid(ScheduledShift $shift): string {
        return 'shift-' . $shift->sqid . '@workdiary';
    }

    public static function vacationUid(Vacation $vacation): string {
        return 'vacation-' . $vacation->sqid . '@workdiary';
    }

    /**
     * Einzel-ICS-Dokument (ein VEVENT) einer Schicht für das CalDAV-Publish
     * (Feature 058, Rang 17). Zeitbasiert, lokale TZ; über Mitternacht → +1 Tag.
     * Voraussetzung: Start-/Endzeit gesetzt (der Aufrufer filtert das).
     */
    public function documentForShift(ScheduledShift $shift): string {
        $tz = new DateTimeZone((string) config('app.timezone', 'Europe/Berlin'));
        $date = $shift->date->format('Y-m-d');
        $start = CarbonImmutable::parse($date . ' ' . $shift->start_time, $tz);
        $end = CarbonImmutable::parse($date . ' ' . $shift->end_time, $tz);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        $label = $shift->shiftType->name ?? __('Schicht');
        $ics = IcsEvent::create((string) $label)
            ->uniqueIdentifier(self::shiftUid($shift))
            ->startsAt($start->toDateTimeImmutable())
            ->endsAt($end->toDateTimeImmutable())
            ->withoutTimezone();

        return Calendar::create((string) $label)
            ->productIdentifier((string) config('events.ics.product_id', '-//workDiary//Schedule//DE'))
            ->event($ics)
            ->get();
    }

    /**
     * Einzel-ICS-Dokument (ein Ganztags-VEVENT) eines Urlaubs für das
     * CalDAV-Publish (Feature 058, Rang 17). DTEND ist bei VALUE=DATE exklusiv
     * → +1 Tag (über fullDay()).
     */
    public function documentForVacation(Vacation $vacation): string {
        $name = (string) __('Urlaub: :name', ['name' => $vacation->user->name ?? '']);
        $vac = IcsEvent::create($name)
            ->uniqueIdentifier(self::vacationUid($vacation))
            ->fullDay()
            ->startsAt($vacation->start_date->copy()->toDateTimeImmutable())
            ->endsAt($vacation->end_date->copy()->addDay()->toDateTimeImmutable());

        return Calendar::create($name)
            ->productIdentifier((string) config('events.ics.product_id', '-//workDiary//Schedule//DE'))
            ->event($vac)
            ->get();
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
            $calendar->event($this->toIcsEvent($event, $tz));
        }

        return $calendar->get();
    }

    /** Bildet ein Event auf eine ICS-Event-Komponente ab (Feed + CalDAV-Publish teilen diese Abbildung). */
    private function toIcsEvent(Event $event, DateTimeZone $tz): IcsEvent {
        $start = $event->started_at->copy()->setTimezone($tz);
        $end = $event->ended_at->copy()->setTimezone($tz);

        $location = $event->rooms
            ->map(fn($r) => trim($r->building . ' ' . $r->name))
            ->filter()
            ->implode(', ');

        $ics = IcsEvent::create($event->title)
            ->uniqueIdentifier(self::eventUid($event))
            ->startsAt($start->toDateTimeImmutable())
            ->endsAt($end->toDateTimeImmutable())
            // DTSTAMP an den Termin binden, nicht an den Erzeugungszeitpunkt:
            // Die Bibliothek setzt sonst `now()`, und damit unterscheidet sich
            // dasselbe unveränderte Ereignis bei jedem Aufruf. Der Publish
            // vergleicht das Dokument über einen Hash — mit wanderndem DTSTAMP
            // gälte jeder Termin bei JEDEM Lauf als geändert und würde erneut
            // hochgeladen (Befund 2026-08-21).
            ->createdAt(($event->updated_at ?? $event->created_at ?? $start)->toDateTimeImmutable())
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

        return $ics;
    }
}
