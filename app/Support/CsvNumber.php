<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formatter für Zahlen in CSV-Exports im deutschen Locale.
 *
 * Konvention:
 *  - Dezimaltrenner: Komma ","
 *  - Tausendertrenner: Punkt "."
 *  - CSV-Spaltentrenner ist ";" (kollidiert nicht).
 */
final class CsvNumber {
    public static function decimal(float|int|string|null $value, int $decimals = 2): string {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, $decimals, ',', '.');
    }
}
