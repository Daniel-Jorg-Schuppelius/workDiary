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
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use DateTimeZone;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\{DateTimeParser, Reader};
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
    /**
     * @param  list<string>  $categoryAllowlist  normalisierte (lowercase) Kategorien; leer = keine Einschränkung
     */
    public function __construct(
        private readonly string $absolutePath,
        private readonly IcalEventMapper $mapper,
        private readonly string $timezone,
        private readonly array $categoryAllowlist = [],
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

        $number = 0;
        foreach ($document->select('VEVENT') as $vevent) {
            if (! $vevent instanceof VEvent) {
                continue;
            }

            $event = $this->extract($vevent);

            if ($event->allDay) {
                yield SourceRow::warning(++$number, $this->skip('import.error.ical.allDay', $event));

                continue;
            }
            if (! $event->hasTime()) {
                yield SourceRow::warning(++$number, $this->skip('import.error.ical.noTime', $event));

                continue;
            }
            if ($this->categoryAllowlist !== [] && ! $this->matchesAllowlist($event)) {
                yield SourceRow::warning(++$number, $this->skip('import.error.ical.category', $event));

                continue;
            }
            if ($this->mapper->skipsTransparent() && $event->transparent) {
                yield SourceRow::warning(++$number, $this->skip('import.error.ical.transparent', $event));

                continue;
            }
            if ($event->recurring) {
                // Serie: Basisinstanz importieren, Expansion als Hinweis melden.
                yield SourceRow::warning(++$number, $this->skip('import.error.ical.recurring', $event));
            }

            yield SourceRow::data(++$number, $this->mapper->toRow($event));
        }
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
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
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
