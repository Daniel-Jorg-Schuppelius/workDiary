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

use CommonToolkit\ValueObjects\Duration;
use InvalidArgumentException;

/**
 * Wandelt die ISO-8601-Dauer der OpenProject-Zeiteinträge (`hours`, z. B.
 * "PT2H30M", "P1DT4H") in Minuten um und zurück. OpenProject liefert die
 * gebuchte Dauer als ISO-8601-Periode; workDiary rechnet intern mit Minuten.
 */
final class OpenProjectDuration {
    /** Parst eine ISO-8601-Dauer in (gerundete) Minuten. Ungültig/leer = 0. */
    public static function toMinutes(?string $iso): int {
        $iso = trim((string) $iso);
        if ($iso === '') {
            return 0;
        }

        try {
            // Sekundenrest kaufmännisch runden (getTotalMinutes() schneidet ab).
            return max(0, (int) round(Duration::fromIso8601($iso)->getTotalSeconds() / 60));
        } catch (InvalidArgumentException) {
            return 0;
        }
    }

    /**
     * Formatiert Minuten als ISO-8601-Dauer (z. B. 150 → "PT2H30M").
     * Bewusst nicht Duration::toIso8601(): die Null bleibt "PT0H" (nicht "PT0S").
     */
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
