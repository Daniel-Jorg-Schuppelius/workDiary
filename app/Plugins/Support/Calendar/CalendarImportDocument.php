<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarImportDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

use DateTimeImmutable;
use Spatie\IcalendarGenerator\Components\{Calendar, Event as IcsEvent};
use Spatie\IcalendarGenerator\Properties\{CalendarAddressProperty, TextProperty};
use Spatie\IcalendarGenerator\ValueObjects\CalendarAddress;

/**
 * Abgerufene Kalendertermine als ein iCalendar-Dokument für den Zeitimport
 * (MVP-976): CalDAV-Objekte werden zusammengeführt, REST-Termine (Google,
 * Microsoft 365) über den ICS-Generator geschrieben.
 */
final class CalendarImportDocument {
    /**
     * VEVENT- und VTIMEZONE-Blöcke mehrerer CalDAV-Objekte in einem Kalender;
     * Zeitzonen je TZID nur einmal.
     *
     * @param  list<string>  $objects
     */
    public function merge(array $objects): string {
        $zones = [];
        $events = [];
        foreach ($objects as $object) {
            if (preg_match_all('/BEGIN:VTIMEZONE.*?END:VTIMEZONE/s', $object, $matches) > 0) {
                foreach ($matches[0] as $zone) {
                    $id = preg_match('/^TZID[^:]*:(.+)$/m', $zone, $tzid) === 1 ? trim($tzid[1]) : $zone;
                    $zones[$id] ??= $zone;
                }
            }
            if (preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $object, $matches) > 0) {
                array_push($events, ...$matches[0]);
            }
        }

        return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//workDiary//Kalenderimport//DE', ...array_values($zones), ...$events, 'END:VCALENDAR']) . "\r\n";
    }

    /**
     * @param  list<array{uid: string, title: string, description: ?string, start: DateTimeImmutable, end: ?DateTimeImmutable, all_day: bool, transparent: bool, categories: list<string>, organizer: ?string}>  $events
     */
    public function fromEvents(array $events): string {
        $calendar = Calendar::create('Import')->productIdentifier('-//workDiary//Kalenderimport//DE');
        foreach ($events as $event) {
            $ics = IcsEvent::create($event['title'] !== '' ? $event['title'] : '—')
                ->uniqueIdentifier($event['uid'])
                ->startsAt($event['start'])
                ->createdAt($event['start']);
            if ($event['end'] !== null) {
                $ics->endsAt($event['end']);
            }
            if ($event['all_day']) {
                $ics->fullDay();
            }
            if (($event['description'] ?? '') !== '') {
                $ics->description((string) $event['description']);
            }
            if ($event['transparent']) {
                $ics->transparent();
            }
            foreach ($event['categories'] as $category) {
                $ics->appendProperty(TextProperty::create('CATEGORIES', $category));
            }
            if (($event['organizer'] ?? '') !== '') {
                $ics->appendProperty(CalendarAddressProperty::create('ORGANIZER', new CalendarAddress((string) $event['organizer'])));
            }
            $calendar->event($ics);
        }

        return $calendar->get();
    }
}
