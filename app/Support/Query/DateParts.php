<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateParts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Query;

use Illuminate\Support\Facades\DB;

/** SQL-Ausdrücke für Datumsteile, die MariaDB und SQLite gleich verstehen. */
final class DateParts {
    /**
     * Jahr und Monat einer Datumsspalte als Ganzzahl (für GROUP BY).
     *
     * @param  literal-string  $column  fester Spaltenname (nie Nutzereingabe — selectRaw)
     * @return array{0: literal-string, 1: literal-string}
     */
    public static function yearMonth(string $column): array {
        return DB::connection()->getDriverName() === 'mysql'
            ? ["YEAR({$column})", "MONTH({$column})"]
            : ["CAST(strftime('%Y', {$column}) AS INTEGER)", "CAST(strftime('%m', {$column}) AS INTEGER)"];
    }
}
