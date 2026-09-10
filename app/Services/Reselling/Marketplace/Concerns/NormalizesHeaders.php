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

use CommonToolkit\Entities\XLSX\{Cell, Sheet};

/**
 * Kopfzeilen der Reseller-Exporte vergleichbar machen (Review 2026-09-10, E):
 * Whitespace gebündelt, klein geschrieben. Ein BOM entfernt bereits
 * `File::readLinesAsUtf8` im Toolkit, deshalb hier kein BOM-Strip mehr.
 */
trait NormalizesHeaders {
    /**
     * @param  bool  $stripUnitSuffix  „Einkaufspreis (EUR)" / „Preis (netto)" → „einkaufspreis" / „preis"
     */
    protected static function normalizeHeader(string $name, bool $stripUnitSuffix = false): string {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));

        return $stripUnitSuffix ? trim(preg_replace('/\s*\((?:eur|€|netto|net)\)\s*$/u', '', $name) ?? $name) : $name;
    }

    /**
     * Normalisierte Überschrift → Spaltenposition (erste Fundstelle gewinnt).
     *
     * @param  iterable<int|string, mixed>  $names
     * @return array<string, int>
     */
    protected static function headerIndex(iterable $names): array {
        $index = [];
        $position = 0;
        foreach ($names as $name) {
            $index[self::normalizeHeader((string) $name)] ??= $position;
            $position++;
        }

        return $index;
    }

    /**
     * Kopfzellen eines Blatts als Text. `Sheet::getHeaderNames()` castet
     * `(string)` und stürzt über einer Datumszelle in der ersten Zeile
     * (Deckblatt „Erstellt am"); `toCanonicalString()` nicht.
     *
     * @return list<string>
     */
    protected static function sheetHeaderNames(Sheet $sheet): array {
        return array_map(static fn(Cell $cell): string => $cell->toCanonicalString(), array_values($sheet->getHeader()?->getCells() ?? []));
    }
}
