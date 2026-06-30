<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Normalize.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

/**
 * Normalisierungs- und Ähnlichkeitshelfer für den Entitäts-Abgleich. Bewusst
 * statisch und zustandslos — von allen {@see MatchStrategy} geteilt.
 */
final class Normalize {
    /** Lowercase, getrimmt, kollabierte Leerzeichen (für Namen/Firmen). */
    public static function text(?string $value): string {
        $value = mb_strtolower(trim((string) $value));

        return (string) preg_replace('/\s+/', ' ', $value);
    }

    /** Wie text(), aber ohne jegliche Leerzeichen (für IDs/Nummern/PLZ/USt-IdNr.). */
    public static function id(?string $value): string {
        return (string) preg_replace('/\s+/', '', mb_strtolower(trim((string) $value)));
    }

    /** Ähnlichkeit zweier Strings als 0..1-Score (similar_text). */
    public static function similarity(string $a, string $b): float {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        similar_text($a, $b, $percent);

        return $percent / 100;
    }
}
