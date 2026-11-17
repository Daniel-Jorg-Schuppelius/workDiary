<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginHours.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\LoginPolicy;

use Carbon\CarbonImmutable;

/**
 * Anmeldezeitfenster je Wochentag (Feature 205, MVP-1654).
 *
 * Je ISO-Wochentag (1 = Montag … 7 = Sonntag) höchstens ein Fenster
 * `[from, until)` in der Ortszeit der Organisation; ein Tag ohne Fenster ist
 * geschlossen. Liegt `until` vor oder auf `from`, reicht das Fenster über
 * Mitternacht in den Folgetag (Nachtdienst 22:00–06:00). `24:00` steht für
 * das Tagesende.
 */
final class LoginHours {
    public const string END_OF_DAY = '24:00';

    private const int MINUTES_PER_DAY = 1440;

    private const string TIME_PATTERN = '/^(?:([01]\d|2[0-3]):([0-5]\d)|24:00)$/';

    /** HH:MM (00:00–23:59) oder 24:00. */
    public static function isValidTime(mixed $value): bool {
        return is_string($value) && preg_match(self::TIME_PATTERN, $value) === 1;
    }

    /** Minuten seit Tagesbeginn; 24:00 = 1440. */
    public static function minutes(string $time): int {
        if ($time === self::END_OF_DAY) {
            return self::MINUTES_PER_DAY;
        }
        [$hour, $minute] = array_map('intval', explode(':', $time, 2));

        return $hour * 60 + $minute;
    }

    /**
     * Formulareingabe (Wochentag => from/until, leere Tage fehlen) in die
     * gespeicherte Form bringen; ungültige oder leere Tage fallen heraus.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<int, array{from: string, until: string}>
     */
    public static function normalize(array $input): array {
        $result = [];
        for ($day = 1; $day <= 7; $day++) {
            $window = $input[$day] ?? $input[(string) $day] ?? null;
            if (! is_array($window)) {
                continue;
            }
            $from = $window['from'] ?? null;
            $until = $window['until'] ?? null;
            if (! self::isValidTime($from) || ! self::isValidTime($until) || $from === self::END_OF_DAY || $from === $until) {
                continue;
            }
            $result[$day] = ['from' => (string) $from, 'until' => (string) $until];
        }

        return $result;
    }

    /**
     * Liegt der Zeitpunkt (Ortszeit der Organisation) in einem Fenster?
     *
     * @param  array<int, array{from: string, until: string}>  $hours
     */
    public static function isOpen(array $hours, CarbonImmutable $local): bool {
        $minute = $local->hour * 60 + $local->minute;
        $day = $local->dayOfWeekIso;

        $today = $hours[$day] ?? null;
        if ($today !== null) {
            [$from, $until] = [self::minutes($today['from']), self::minutes($today['until'])];
            if ($until > $from ? ($minute >= $from && $minute < $until) : $minute >= $from) {
                return true;
            }
        }

        // Fenster des Vortags, das über Mitternacht reicht.
        $previous = $hours[$day === 1 ? 7 : $day - 1] ?? null;
        if ($previous !== null) {
            [$from, $until] = [self::minutes($previous['from']), self::minutes($previous['until'])];
            if ($until <= $from && $minute < $until) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nächster Fensterbeginn nach dem Zeitpunkt (Ortszeit); null ohne
     * Fenster in den kommenden sieben Tagen.
     *
     * @param  array<int, array{from: string, until: string}>  $hours
     */
    public static function nextOpening(array $hours, CarbonImmutable $local): ?CarbonImmutable {
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $local->startOfDay()->addDays($offset);
            $window = $hours[$date->dayOfWeekIso] ?? null;
            if ($window === null) {
                continue;
            }
            $start = self::minutes($window['from']);
            $opening = $date->setTime(intdiv($start, 60), $start % 60);
            if ($opening->greaterThan($local)) {
                return $opening;
            }
        }

        return null;
    }
}
