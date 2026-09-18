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

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Normalisierungs- und Ähnlichkeitshelfer für den Entitäts-Abgleich. Bewusst
 * statisch und zustandslos — von allen {@see MatchStrategy} geteilt.
 */
final class Normalize {
    /** Lowercase, getrimmt, kollabierte Leerzeichen (für Namen/Firmen). */
    public static function text(?string $value): string {
        return StringHelper::normalizeWhitespace(mb_strtolower((string) $value));
    }

    /** Wie text(), aber ohne jegliche Leerzeichen (für IDs/Nummern/PLZ/USt-IdNr.). */
    public static function id(mixed $value): string {
        // Value Objects (Iban, VatNumber, Gtin, …) tragen ihre Kennung in
        // getValue() — ohne diese Entnahme verglichen Matcher leere Strings.
        if (is_object($value) && method_exists($value, 'getValue')) {
            $value = $value->getValue();
        }

        return StringHelper::removeWhitespace(mb_strtolower(is_scalar($value) ? (string) $value : ''));
    }

    /** Ähnlichkeit zweier Strings als 0..1-Score (Toolkit, B20/v1.26). */
    public static function similarity(string $a, string $b): float {
        return StringHelper::similarity($a, $b);
    }
}
