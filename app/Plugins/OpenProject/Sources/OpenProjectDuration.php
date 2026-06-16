<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectDuration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Sources;

/**
 * Wandelt die ISO-8601-Dauer der OpenProject-Zeiteinträge (`hours`, z. B.
 * "PT2H30M", "P1DT4H") in Minuten um und zurück. OpenProject liefert die
 * gebuchte Dauer als ISO-8601-Periode; workDiary rechnet intern mit Minuten.
 */
final class OpenProjectDuration {
    private const MINUTES_PER_DAY = 24 * 60;

    private const MINUTES_PER_WEEK = 7 * self::MINUTES_PER_DAY;

    /** Parst eine ISO-8601-Dauer in (gerundete) Minuten. Ungültig/leer = 0. */
    public static function toMinutes(?string $iso): int {
        $iso = trim((string) $iso);
        if ($iso === '') {
            return 0;
        }

        if (! preg_match('/^P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?$/', $iso, $m)) {
            return 0;
        }

        $weeks = (int) ($m[1] ?? 0);
        $days = (int) ($m[2] ?? 0);
        $hours = (int) ($m[3] ?? 0);
        $minutes = (int) ($m[4] ?? 0);
        $seconds = (float) ($m[5] ?? 0);

        $total = $weeks * self::MINUTES_PER_WEEK
            + $days * self::MINUTES_PER_DAY
            + $hours * 60
            + $minutes
            + $seconds / 60;

        return max(0, (int) round($total));
    }

    /** Formatiert Minuten als ISO-8601-Dauer (z. B. 150 → "PT2H30M"). */
    public static function fromMinutes(int $minutes): string {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours === 0 && $mins === 0) {
            return 'PT0H';
        }

        $out = 'PT';
        if ($hours > 0) {
            $out .= $hours . 'H';
        }
        if ($mins > 0) {
            $out .= $mins . 'M';
        }

        return $out;
    }
}
