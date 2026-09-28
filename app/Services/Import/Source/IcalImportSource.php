<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalImportSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source;

use App\Enums\Import\ImportErrorCode;
use App\Services\Import\{EntitySpec, ValidationIssue};
use App\Services\Import\Source\Ical\IcalEvent;
use CommonToolkit\Entities\ICalendar\{Document, Event};
use CommonToolkit\Helper\Data\EmailHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use CommonToolkit\Parsers\ICalendarParser;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * iCal-Quelle (MVP-438) auf dem iCalendar-Parser des common-toolkit (MVP-965).
 *
 * Liest `VEVENT`s aus einer `.ics`-Datei (insbesondere Outlook-Exporte) und
 * bildet sie über einen {@see IcalEventMapper} auf die kanonischen Spalten der
 * Ziel-Entität ab. Bewusst konservativ:
 *
 * - **Ganztags-Events** und Events **ohne Uhrzeit** werden übersprungen
 *   (Hinweiszeile) — sie sind keine belastbaren Kommen/Gehen-Intervalle.
 * - **`TRANSP:TRANSPARENT`** (frei/OOF) wird für Stempelungen übersprungen
 *   ({@see IcalEventMapper::skipsTransparent()}).
 * - **Serien (`RRULE`)** werden im MVP nur mit ihrer Basisinstanz gelesen;
 *   die Expansion ist „Später" (Hinweiszeile im Preflight).
 * - **Kategorie-Allowlist** (optional je Lauf): nur Events mit passender
 *   `CATEGORIES`-Angabe werden übernommen — damit ein voller Kalender nicht
 *   pauschal als Anwesenheit gilt.
 *
 * Zeitzonen: `DTSTART;TZID=…`/`Z` werden über die übergebene Org-Zeitzone in
 * lokale `date`/`time` überführt (keine stille UTC-Verschiebung).
 */
final class IcalImportSource implements ImportSource {
    /** Obergrenze je Serie, damit eine fehlerhafte Regel den Import nicht aufbläht. */
    private const MAX_OCCURRENCES = 1000;

    /**
     * @param  list<string>  $categoryAllowlist  normalisierte (lowercase) Kategorien; leer = keine Einschränkung
     * @param  array{0: DateTimeImmutable, 1: DateTimeImmutable}|null  $recurrenceWindow  Zeitraum der Serien-Auflösung (MVP-885); null = nur Basisinstanz
     */
    public function __construct(
        private readonly string $absolutePath,
        private readonly IcalEventMapper $mapper,
        private readonly string $timezone,
        private readonly array $categoryAllowlist = [],
        private readonly ?array $recurrenceWindow = null,
    ) {}

    public function headerIssues(EntitySpec $spec): array {
        return [];
    }

    public function rows(EntitySpec $spec): iterable {
        try {
            $document = ICalendarParser::fromString(ToolkitFile::read($this->absolutePath));
        } catch (Throwable $e) {
            throw new \RuntimeException((string) __('import.error.format.parse', ['reason' => $e->getMessage()]), 0, $e);
        }

        // Serie und ihre abweichenden Einzeltermine teilen die UID.
        $number = 0;
        foreach ($document->getSeries() as $group) {
            $master = null;
            foreach ($group as $event) {
                if ($event->has('RRULE') && ! $event->has('RECURRENCE-ID')) {
                    $master = $event;
                }
            }

            if ($master === null || $this->recurrenceWindow === null) {
                foreach ($group as $event) {
                    yield from $this->emit($this->extract($event), $number);
                }

                continue;
            }

            $occurrences = $this->occurrences($group);
            if ($occurrences === []) {
                yield SourceRow::warning(++$number, $this->skip('import.error.ical.recurringOutside', $this->extract($master)));
            }
            foreach ($occurrences as $occurrence) {
                yield from $this->emit($occurrence, $number);
            }
        }
    }

    /** @return iterable<SourceRow> */
    private function emit(IcalEvent $event, int &$number): iterable {
        if ($event->allDay) {
            yield SourceRow::warning(++$number, $this->skip('import.error.ical.allDay', $event));

            return;
        }
        if (! $event->hasTime()) {
            yield SourceRow::warning(++$number, $this->skip('import.error.ical.noTime', $event));

            return;
        }
        if ($this->categoryAllowlist !== [] && ! $this->matchesAllowlist($event)) {
            yield SourceRow::warning(++$number, $this->skip('import.error.ical.category', $event));

            return;
        }
        if ($this->mapper->skipsTransparent() && $event->transparent) {
            yield SourceRow::warning(++$number, $this->skip('import.error.ical.transparent', $event));

            return;
        }
        if ($event->recurring) {
            // Ohne Auflösungszeitraum: Basisinstanz importieren, Hinweis melden.
            yield SourceRow::warning(++$number, $this->skip('import.error.ical.recurring', $event));
        }

        yield SourceRow::data(++$number, $this->mapper->toRow($event));
    }

    /**
     * Vorkommen einer Serie im Zeitraum; EXDATE und abweichende Einzeltermine
     * (RECURRENCE-ID) löst das Toolkit auf. Jedes Vorkommen bekommt eine
     * eigene UID `uid#JJJJMMTTTHHMMSS`, damit ein erneuter Import idempotent bleibt.
     *
     * @param  list<Event>  $group
     * @return list<IcalEvent>
     */
    private function occurrences(array $group): array {
        [$from, $until] = $this->recurrenceWindow ?? throw new \LogicException('no recurrence window');
        $tz = new DateTimeZone($this->timezone);
        try {
            $instances = Document::expand($group, $from, $until, $tz, self::MAX_OCCURRENCES);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($instances as $instance) {
            $base = $this->extract($instance->getEvent(), $instance->getStart(), $instance->getEnd());
            $out[] = new IcalEvent(
                uid: $instance->getEvent()->getUid() . '#' . $instance->getStart()->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis'),
                date: $base->date,
                startTime: $base->startTime,
                endTime: $base->endTime,
                summary: $base->summary,
                description: $base->description,
                email: $base->email,
                categories: $base->categories,
                allDay: $base->allDay,
                transparent: $base->transparent,
                recurring: false,
            );
        }

        return $out;
    }

    private function skip(string $key, IcalEvent $event): ValidationIssue {
        $label = $event->summary !== '' ? $event->summary : $event->uid;

        return new ValidationIssue(
            ImportErrorCode::Skipped,
            null,
            (string) __($key, ['event' => $label]),
        );
    }

    private function matchesAllowlist(IcalEvent $event): bool {
        foreach ($event->categories as $category) {
            if (in_array(mb_strtolower(trim($category)), $this->categoryAllowlist, true)) {
                return true;
            }
        }

        return false;
    }

    /** Ohne $start/$end gelten DTSTART/DTEND bzw. DURATION des Termins; ein Serienvorkommen übergibt seine Zeiten. */
    private function extract(Event $event, ?DateTimeImmutable $start = null, ?DateTimeImmutable $end = null): IcalEvent {
        $tz = new DateTimeZone($this->timezone);
        $uid = $event->getUid();
        // Einzeltermin einer Serie ohne Serie in der Datei: eigene Kennung je Vorkommen.
        $recurrenceId = $event->getRecurrenceId($tz);
        if ($recurrenceId !== null) {
            $uid .= '#' . $recurrenceId->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis');
        }
        $allDay = $event->isAllDay();

        $date = $startTime = $endTime = null;
        $start ??= $event->getStart($tz);
        if ($start !== null && ! $allDay) {
            $start = $start->setTimezone($tz);
            $date = $start->format('Y-m-d');
            $startTime = $start->format('H:i');
            if ($end === null) {
                $duration = $event->getDuration();
                $end = $event->endHasTime() ? $event->getEnd($tz) : ($duration !== null ? $start->add($duration) : null);
            }
            if ($end !== null) {
                $endTime = $end->setTimezone($tz)->format('H:i');
            }
        } elseif ($allDay && $start !== null) {
            $date = $start->format('Y-m-d');
        }

        return new IcalEvent(
            uid: $uid,
            date: $date,
            startTime: $startTime,
            endTime: $endTime,
            summary: $event->getSummary(),
            description: $event->getDescription(),
            email: $this->resolveEmail($event),
            categories: $event->getCategories(),
            allDay: $allDay,
            transparent: $event->isTransparent(),
            recurring: $event->has('RRULE'),
        );
    }

    private function resolveEmail(Event $event): ?string {
        foreach (array_filter([$event->getOrganizer(), ...$event->getAttendees()]) as $candidate) {
            $value = trim($candidate);
            if (EmailHelper::isEmail($value)) {
                return mb_strtolower($value);
            }
        }

        return null;
    }
}
