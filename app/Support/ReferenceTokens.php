<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReferenceTokens.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Was im Text eine Beleg-, Bestell- oder Projektnummer sein kann, und ihr
 * Vergleichsschlüssel. Eine Lesart für Mail-Eingang, Rechnungsnachfrage und
 * PDF-Erkennung (Konsolidierungs-Audit 2026-10-21, ko-08): vorher zählte
 * „RE 2026/0012“ je Leser anders.
 */
final class ReferenceTokens {
    /** Buchstabenpräfix + Ziffern mit -/ Trennern („RE-2026-12“, „AB/3301“). */
    private const PREFIXED = '/\b[A-Za-z]{0,5}[-\/]?\d{2,}(?:[-\/]\d+)*\b/u';

    /** Freiere Form mit Punkt und Unterstrich („B.2026.15“, „P_0012“), mit mindestens einer Ziffer. */
    private const FREE = '/[\p{L}\d][\p{L}\d\-\/._]{2,38}[\p{L}\d]/u';

    /** Präfix mit Leerzeichen vor der Nummer („RE 2026/0012“). */
    private const SPACED = '/\b([A-Za-z]{1,5}) ([-\/]?\d{2,}(?:[-\/]\d+)*)\b/u';

    /**
     * Kandidaten in Original- und Großschreibung; die Datenbank vergleicht die
     * gespeicherten Nummern unverändert.
     *
     * @return list<string>
     */
    public static function fromText(string $text, int $limit = 60): array {
        $found = [];
        foreach ([self::PREFIXED, self::FREE] as $pattern) {
            if (preg_match_all($pattern, $text, $matches) > 0) {
                foreach ($matches[0] as $token) {
                    $found[] = $token;
                }
            }
        }
        if (preg_match_all(self::SPACED, $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as [$whole, $prefix, $number]) {
                $found[] = $whole;
                $found[] = $prefix . $number;
            }
        }

        $tokens = [];
        foreach ($found as $token) {
            $token = trim($token);
            if (mb_strlen($token) < 3 || preg_match('/\d/', $token) !== 1) {
                continue;
            }
            $tokens[$token] = true;
            $tokens[mb_strtoupper($token)] = true;
            if (count($tokens) >= $limit) {
                break;
            }
        }

        return array_slice(array_map('strval', array_keys($tokens)), 0, $limit);
    }

    /** Vergleichsschlüssel einer Nummer: ohne Leerraum, Großschreibung, höchstens 64 Zeichen. */
    public static function key(mixed $number): ?string {
        $key = is_scalar($number) ? mb_strtoupper(StringHelper::removeWhitespace((string) $number, true)) : '';

        return $key !== '' ? mb_substr($key, 0, 64) : null;
    }
}
