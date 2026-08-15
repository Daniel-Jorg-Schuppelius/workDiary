<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XlsxCellValue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use CommonToolkit\Entities\XLSX\Cell;
use DateTimeInterface;

/**
 * Normalisiert XLSX-Zellwerte auf die String-Repräsentation der CSV-Pfade
 * (Datum `Y-m-d` bzw. `Y-m-d H:i:s`, Zahlen mit Dezimalpunkt, Bool als 1/0).
 * `Cell::getStringValue()` allein reicht nicht: DateTime-Werte sind nicht
 * string-castbar und Floats kippen in Exponentialschreibweise.
 */
final class XlsxCellValue {
    public static function toString(Cell $cell): string {
        $value = $cell->getValue();

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00'
                ? $value->format('Y-m-d')
                : $value->format('Y-m-d H:i:s');
        }
        if (is_float($value)) {
            // Kein (string)-Cast: vermeidet Exponentialschreibweise.
            $formatted = number_format($value, 10, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        return $cell->getStringValue();
    }
}
