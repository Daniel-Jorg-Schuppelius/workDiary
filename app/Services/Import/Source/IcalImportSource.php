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
use CommonToolkit\Helper\Data\EmailHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use DateTimeImmutable;
use DateTimeZone;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\{DateTimeParser, Reader};
use Sabre\VObject\Recur\EventIterator;
use Throwable;

/**
 * iCal-Quelle (MVP-438) auf `sabre/vobject`.
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
            $document = Reader::read(
                ToolkitFile::read($this->absolutePath),
                Reader::OPTION_FORGIVING | Reader::OPTION_IGNORE_INVALID_LINES,
            );
        } catch (Throwable $e) {
            throw new \RuntimeException((string) __('import.error.format.parse', ['reason' => $e->getMessage()]), 0, $e);
        }

        // Serie und ihre abweichenden Einzeltermine teilen die UID.
        $groups = [];
        foreach ($document->select('VEVENT') as $vevent) {
            if ($vevent instanceof VEvent) {
                $groups[trim((string) ($vevent->UID ?? '')) ?: spl_object_id($vevent)][] = $vevent;
            }
        }

        $number = 0;
        foreach ($groups as $group) {
            $master = null;
            foreach ($group as $vevent) {
                if (isset($vevent->RRULE) && ! isset($vevent->{'RECURRENCE-ID'})) {
                    $master = $vevent;
                }
            }

            if ($master === null || $this->recurrenceWindow === null) {
                foreach ($group as $vevent) {
                    yield from $this->emit($this->extract($vevent), $number);
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
     * (RECURRENCE-ID) löst der Iterator auf. Jedes Vorkommen bekommt eine
     * eigene UID `uid#JJJJMMTTTHHMMSS`, damit ein erneuter Import idempotent bleibt.
     *
     * @param  list<VEvent>  $group
     * @return list<IcalEvent>
     */
    private function occurrences(array $group): array {
        [$from, $until] = $this->recurrenceWindow ?? throw new \LogicException('no recurrence window');
        $tz = new DateTimeZone($this->timezone);
        try {
            $iterator = new EventIterator($group, null, $tz);
            $iterator->fastForward($from);
        } catch (Throwable) {
            return [];
        }

        $out = [];
        while ($iterator->valid() && $iterator->getDtStart() < $until && count($out) < self::MAX_OCCURRENCES) {
            $occurrence = $iterator->getEventObject();
            $base = $this->extract($occurrence);
            $out[] = new IcalEvent(
                uid: trim((string) ($occurrence->UID ?? '')) . '#' . $iterator->getDtStart()->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis'),
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
            $iterator->next();
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

    private function extract(VEvent $vevent): IcalEvent {
        $tz = new DateTimeZone($this->timezone);
        $uid = trim((string) ($vevent->UID ?? ''));
        // Einzeltermin einer Serie ohne Serie in der Datei: eigene Kennung je Vorkommen.
        if (isset($vevent->{'RECURRENCE-ID'})) {
            $uid .= '#' . $vevent->{'RECURRENCE-ID'}->getDateTime()->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis');
        }
        $summary = trim((string) ($vevent->SUMMARY ?? ''));
        $description = trim((string) ($vevent->DESCRIPTION ?? ''));
        $transparent = mb_strtoupper(trim((string) ($vevent->TRANSP ?? ''))) === 'TRANSPARENT';
        $recurring = isset($vevent->RRULE);

        $dtstart = $vevent->DTSTART ?? null;
        $allDay = $dtstart !== null && ! $dtstart->hasTime();

        $date = $startTime = $endTime = null;
        if ($dtstart !== null && $dtstart->hasTime()) {
            $start = $dtstart->getDateTime($tz)->setTimezone($tz);
            $date = $start->format('Y-m-d');
            $startTime = $start->format('H:i');

            $end = null;
            if (isset($vevent->DTEND) && $vevent->DTEND->hasTime()) {
                $end = $vevent->DTEND->getDateTime($tz)->setTimezone($tz);
            } elseif (isset($vevent->DURATION)) {
                $interval = DateTimeParser::parseDuration((string) $vevent->DURATION);
                if ($interval instanceof \DateInterval) {
                    $end = $start->add($interval);
                }
            }
            if ($end !== null) {
                $endTime = $end->format('H:i');
            }
        } elseif ($allDay) {
            $date = $dtstart->getDateTime($tz)->format('Y-m-d');
        }

        return new IcalEvent(
            uid: $uid,
            date: $date,
            startTime: $startTime,
            endTime: $endTime,
            summary: $summary,
            description: $description,
            email: $this->resolveEmail($vevent),
            categories: $this->resolveCategories($vevent),
            allDay: $allDay,
            transparent: $transparent,
            recurring: $recurring,
        );
    }

    private function resolveEmail(VEvent $vevent): ?string {
        $candidates = [];
        if (isset($vevent->ORGANIZER)) {
            $candidates[] = (string) $vevent->ORGANIZER;
        }
        foreach ($vevent->select('ATTENDEE') as $attendee) {
            $candidates[] = (string) $attendee;
        }

        foreach ($candidates as $candidate) {
            $value = trim($candidate);
            if (stripos($value, 'mailto:') === 0) {
                $value = substr($value, 7);
            }
            $value = trim($value);
            if (EmailHelper::isEmail($value)) {
                return mb_strtolower($value);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function resolveCategories(VEvent $vevent): array {
        $categories = [];
        foreach ($vevent->select('CATEGORIES') as $property) {
            foreach ((array) $property->getParts() as $part) {
                $value = trim((string) $part);
                if ($value !== '') {
                    $categories[] = $value;
                }
            }
        }

        return array_values(array_unique($categories));
    }
}
