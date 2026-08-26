<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DrivingTimeRules.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use Carbon\CarbonImmutable;

/**
 * Regelwerk Lenk- und Ruhezeiten (Feature 144, MVP-719) — REIN, ohne DB.
 *
 * Grenzwerte nach VO (EG) Nr. 561/2006 (Art. 4, 6, 7, 8); die FPersV
 * (§ 1 Abs. 1 für 2,8–3,5 t; Taxi-/Mietwagen-Betriebe je nach Einordnung)
 * verweist auf dieselben Grenzwerte. Die Software bildet nur diese Zahlen
 * ab und entscheidet NICHT, welche Vorschrift im Einzelfall gilt — das ist
 * Sache des Betriebs (keine Rechtsberatung). Alle Werte in Minuten.
 *
 * Bewusste Vereinfachungen (dokumentiert in features/144):
 *  - „Tag" = Kalendertag der Fahrt-Startzeit (statt 24-h-Zeitraum nach Ende
 *    der letzten Ruhezeit), „Woche" = ISO-Woche (Art. 4 lit. i: Mo 00:00 –
 *    So 24:00 — identisch).
 *  - Fahrzeit = Dauer der erfassten Fahrten (TravelLog), Pausen = Lücken
 *    zwischen Fahrten; andere Arbeit (Be-/Entladen) ist nicht unterscheidbar
 *    und zählt als Unterbrechung, sobald die Lücke lang genug ist.
 */
final class DrivingTimeRules {
    /** Art. 6 Abs. 1 S. 1: tägliche Lenkzeit höchstens 9 h. */
    public const DAILY_DRIVING_MINUTES = 540;

    /** Art. 6 Abs. 1 S. 2: höchstens zweimal pro Woche Verlängerung auf 10 h. */
    public const DAILY_DRIVING_EXTENDED_MINUTES = 600;

    /** Art. 6 Abs. 1 S. 2: Anzahl zulässiger 10-h-Tage je Woche. */
    public const DAILY_DRIVING_EXTENSIONS_PER_WEEK = 2;

    /** Art. 6 Abs. 2: wöchentliche Lenkzeit höchstens 56 h. */
    public const WEEKLY_DRIVING_MINUTES = 3360;

    /** Art. 6 Abs. 3: Lenkzeit in zwei aufeinanderfolgenden Wochen höchstens 90 h. */
    public const FORTNIGHT_DRIVING_MINUTES = 5400;

    /** Art. 7 S. 1: nach 4,5 h Lenkzeit ist eine Fahrtunterbrechung fällig. */
    public const BREAK_AFTER_DRIVING_MINUTES = 270;

    /** Art. 7 S. 1: Fahrtunterbrechung mindestens 45 min. */
    public const BREAK_MINUTES = 45;

    /** Art. 7 S. 2: Aufteilung in erste Unterbrechung ≥ 15 min … */
    public const BREAK_SPLIT_FIRST_MINUTES = 15;

    /** Art. 7 S. 2: … gefolgt von einer zweiten Unterbrechung ≥ 30 min. */
    public const BREAK_SPLIT_SECOND_MINUTES = 30;

    /** Art. 4 lit. g / Art. 8 Abs. 2: regelmäßige tägliche Ruhezeit 11 h. */
    public const DAILY_REST_MINUTES = 660;

    /** Art. 4 lit. g: reduzierte tägliche Ruhezeit mindestens 9 h. */
    public const DAILY_REST_REDUCED_MINUTES = 540;

    /** Art. 8 Abs. 4: höchstens drei reduzierte tägliche Ruhezeiten zwischen zwei wöchentlichen Ruhezeiten. */
    public const DAILY_REST_REDUCTIONS_PER_WEEK = 3;

    /** Art. 8 Abs. 2: tägliche Ruhezeit innerhalb von 24 h nach Ende der vorhergehenden Ruhezeit. */
    public const DAILY_WINDOW_MINUTES = 1440;

    /** Art. 4 lit. h / Art. 8 Abs. 6: regelmäßige wöchentliche Ruhezeit 45 h. */
    public const WEEKLY_REST_MINUTES = 2700;

    /** Art. 4 lit. h: reduzierte wöchentliche Ruhezeit mindestens 24 h (Ausgleich bis Ende der dritten Folgewoche, Art. 8 Abs. 6). */
    public const WEEKLY_REST_REDUCED_MINUTES = 1440;

    public const REST_REGULAR = 'regular';

    public const REST_REDUCED = 'reduced';

    public const REST_INSUFFICIENT = 'insufficient';

    /** Zulässige Tageslenkzeit, solange die Verlängerungen der Woche nicht verbraucht sind. */
    public static function dailyLimitMinutes(int $extensionsUsedInWeek): int {
        return $extensionsUsedInWeek < self::DAILY_DRIVING_EXTENSIONS_PER_WEEK
            ? self::DAILY_DRIVING_EXTENDED_MINUTES
            : self::DAILY_DRIVING_MINUTES;
    }

    /** Tag verbraucht eine der zwei Verlängerungen (> 9 h)? */
    public static function isExtendedDay(int $drivingMinutes): bool {
        return $drivingMinutes > self::DAILY_DRIVING_MINUTES;
    }

    /**
     * Tageslenkzeit einer Woche bewerten: > 10 h ist immer ein Verstoß, > 9 h
     * verbraucht eine Verlängerung — ab der dritten gilt wieder 9 h.
     *
     * @param  array<string, int>  $minutesByDate  Lenkminuten je Kalendertag (Y-m-d) EINER Woche
     * @return list<array{date: string, value: int, threshold: int}>
     */
    public static function evaluateWeekDailyDriving(array $minutesByDate): array {
        ksort($minutesByDate);
        $extensions = 0;
        $violations = [];
        foreach ($minutesByDate as $date => $minutes) {
            if ($minutes > self::DAILY_DRIVING_EXTENDED_MINUTES) {
                $violations[] = ['date' => (string) $date, 'value' => $minutes, 'threshold' => self::DAILY_DRIVING_EXTENDED_MINUTES];
                $extensions++;

                continue;
            }
            if (! self::isExtendedDay($minutes)) {
                continue;
            }
            $extensions++;
            if ($extensions > self::DAILY_DRIVING_EXTENSIONS_PER_WEEK) {
                $violations[] = ['date' => (string) $date, 'value' => $minutes, 'threshold' => self::DAILY_DRIVING_MINUTES];
            }
        }

        return $violations;
    }

    /**
     * Fahrtunterbrechungen (Art. 7) über chronologische Fahrten: Lenkzeit
     * summiert sich, bis eine Lücke ≥ 45 min folgt oder eine Lücke ≥ 15 min
     * später durch eine Lücke ≥ 30 min ergänzt wird (geteilte Unterbrechung).
     * Kürzere Lücken zählen weder als Fahrt noch als Unterbrechung.
     *
     * @param  list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}>  $trips  chronologisch sortiert
     * @return array{violations: list<array{date: string, value: int}>, accumulated: int, max_accumulated: int, partial_break: bool}
     */
    public static function evaluateBreaks(array $trips): array {
        $accumulated = 0;
        $maxAccumulated = 0;
        $partial = false;
        $violations = [];
        // Laufende Episode ohne gültige Unterbrechung: Datum des Überschreitens + bisherige Lenkzeit.
        $episodeDate = null;
        $episodeValue = 0;
        $prevEnd = null;

        foreach ($trips as $trip) {
            if ($prevEnd !== null) {
                $gap = max(0, (int) $prevEnd->diffInMinutes($trip['started_at'], false));
                if ($gap >= self::BREAK_MINUTES || ($partial && $gap >= self::BREAK_SPLIT_SECOND_MINUTES)) {
                    if ($episodeDate !== null) {
                        $violations[] = ['date' => $episodeDate, 'value' => $episodeValue];
                        $episodeDate = null;
                    }
                    $accumulated = 0;
                    $partial = false;
                } elseif ($gap >= self::BREAK_SPLIT_FIRST_MINUTES) {
                    $partial = true;
                }
            }

            $accumulated += max(0, (int) $trip['started_at']->diffInMinutes($trip['ended_at'], false));
            $maxAccumulated = max($maxAccumulated, $accumulated);
            if ($accumulated > self::BREAK_AFTER_DRIVING_MINUTES) {
                $episodeDate ??= $trip['started_at']->toDateString();
                $episodeValue = $accumulated;
            }
            $prevEnd = $trip['ended_at'];
        }
        if ($episodeDate !== null) {
            $violations[] = ['date' => $episodeDate, 'value' => $episodeValue];
        }

        return [
            'violations' => $violations,
            'accumulated' => $accumulated,
            'max_accumulated' => $maxAccumulated,
            'partial_break' => $partial,
        ];
    }

    /** Tägliche Ruhezeit einordnen (regelmäßig ≥ 11 h, reduziert ≥ 9 h, sonst unzureichend). */
    public static function classifyDailyRest(int $gapMinutes): string {
        return match (true) {
            $gapMinutes >= self::DAILY_REST_MINUTES => self::REST_REGULAR,
            $gapMinutes >= self::DAILY_REST_REDUCED_MINUTES => self::REST_REDUCED,
            default => self::REST_INSUFFICIENT,
        };
    }

    /** Wöchentliche Ruhezeit einordnen (regelmäßig ≥ 45 h, reduziert ≥ 24 h, sonst unzureichend). */
    public static function classifyWeeklyRest(int $gapMinutes): string {
        return match (true) {
            $gapMinutes >= self::WEEKLY_REST_MINUTES => self::REST_REGULAR,
            $gapMinutes >= self::WEEKLY_REST_REDUCED_MINUTES => self::REST_REDUCED,
            default => self::REST_INSUFFICIENT,
        };
    }
}
