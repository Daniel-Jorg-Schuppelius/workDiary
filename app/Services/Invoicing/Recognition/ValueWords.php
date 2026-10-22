<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValueWords.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use CommonToolkit\Helper\Data\{AmountTokenizer, DateHelper};
use CommonToolkit\Helper\Data\StringHelper;

/**
 * Ein Wertleser für Heuristik und Zonen (Konsolidierungs-Audit 2026-10-21, ko-02):
 * ob ein Wort ein Betrag oder ein Datum ist, entscheidet das Toolkit — DE-, EN-
 * und CH-Schreibweise gleichermaßen, nicht je Stelle eine eigene Regel.
 */
final class ValueWords {
    /** Ein Wort ist ein Betrag, wenn der Tokenizer es von Anfang an liest und dahinter keine Ziffer mehr folgt („1.190,00€“). */
    public static function isAmount(string $word): bool {
        foreach (AmountTokenizer::tokens($word) as $token) {
            return $token->start === 0 && preg_match('/\d/', mb_substr($word, $token->end)) !== 1;
        }

        return false;
    }

    /** Ein ganzes Datumswort (1.2.2026, 01.02.26, 2026-02-01, 15/03/2026), das der Kalender annimmt — „31.02.2026“ nicht. */
    public static function isDate(string $word): bool {
        return preg_match('/^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}$|^\d{4}-\d{2}-\d{2}$/', $word) === 1 && DateHelper::isDate($word);
    }

    /** Eine Zeile, die schon Werte trägt (Datum oder Betrag), ist keine reine Kopfzeile. */
    public static function lineHasValue(string $line): bool {
        foreach (StringHelper::words($line) as $word) {
            if ($word !== '' && self::isDate(trim($word, " \t:;,"))) {
                return true;
            }
        }

        return AmountTokenizer::tokens($line) !== [];
    }
}
