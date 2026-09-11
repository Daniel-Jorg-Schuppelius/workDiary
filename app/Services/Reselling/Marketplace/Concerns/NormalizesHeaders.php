<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NormalizesHeaders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace\Concerns;

use CommonToolkit\Entities\CSV\HeaderLine;
use CommonToolkit\Entities\XLSX\Sheet;

/**
 * Spaltenpositionen der Reseller-Exporte. Den toleranten Vergleich der
 * Überschriften (BOM, Whitespace, Groß-/Kleinschreibung, Datumszellen in
 * der Kopfzeile) macht seit Toolkit v1.32 `getColumnIndex(…, normalized: true)`
 * auf `Sheet` und `HeaderLine`; hier bleibt nur die Sammlung je gesuchter
 * Spalte (Review 2026-09-10, E).
 */
trait NormalizesHeaders {
    /**
     * Gesuchte Überschrift → Spaltenposition (erste Fundstelle gewinnt);
     * nicht gefundene Spalten fehlen im Ergebnis.
     *
     * @param  list<string>  $columns
     * @return array<string, int>
     */
    protected static function columnPositions(Sheet|HeaderLine $header, array $columns): array {
        $index = [];
        foreach ($columns as $column) {
            $position = $header->getColumnIndex($column, normalized: true);
            if ($position !== null) {
                $index[$column] = $position;
            }
        }

        return $index;
    }
}
