<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HeaderMapper.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Import\ImportErrorCode;

/**
 * Kopfzeilen-Zuordnung einer Quelldatei auf die kanonischen Spalten einer
 * {@see EntitySpec} (MVP-707): eine Logik für CSV-Datei, Direktimport und
 * das manifest.csv im Dokument-ZIP — vorher lag sie zweimal privat in
 * CsvImportSource und DirectCsvImportService.
 */
final class HeaderMapper {
    /**
     * Roh-Kopfzelle → kanonischer Code (null = unbekannte Spalte, wird ignoriert).
     *
     * @param  list<string>  $rawHeader
     * @param  array<string, string>  $saved  gespeicherte Zuordnungen der Organisation (MVP-1020)
     * @return array<int, string|null>
     */
    public static function map(EntitySpec $spec, array $rawHeader, array $saved = []): array {
        $aliases = self::aliasMap($spec, $saved);
        $out = [];
        foreach ($rawHeader as $i => $cell) {
            $out[$i] = $aliases[self::normKey($cell)] ?? null;
        }

        return $out;
    }

    /**
     * Doppelte bzw. fehlende Pflichtspalten der Kopfzeile.
     *
     * @param  list<string>  $rawHeader
     * @param  array<string, string>  $saved
     * @return list<ValidationIssue>
     */
    public static function issues(EntitySpec $spec, array $rawHeader, array $saved = []): array {
        $seen = [];
        $issues = [];
        foreach (self::map($spec, $rawHeader, $saved) as $canonical) {
            if ($canonical === null) {
                continue;
            }
            if (isset($seen[$canonical])) {
                $issues[] = new ValidationIssue(
                    ImportErrorCode::HeaderUnknown,
                    $canonical,
                    (string) __('import.error.header.duplicate', ['column' => $canonical]),
                );
            } else {
                $seen[$canonical] = true;
            }
        }

        foreach ($spec->requiredColumns() as $required) {
            if (! isset($seen[$required])) {
                $issues[] = new ValidationIssue(
                    ImportErrorCode::HeaderMissing,
                    $required,
                    (string) __('import.error.header.missing', ['column' => $required]),
                );
            }
        }

        return $issues;
    }

    /**
     * Wendet die Zuordnung auf eine Datenzeile an (positionsbasiert).
     *
     * @param  array<int|string, string>  $raw
     * @param  array<int, string|null>  $headerMap
     * @return array<string, string>
     */
    public static function apply(array $raw, array $headerMap): array {
        $values = array_values($raw);
        $out = [];
        foreach ($headerMap as $i => $canonical) {
            if ($canonical === null) {
                continue;
            }
            $out[$canonical] = $values[$i] ?? '';
        }

        return $out;
    }

    /**
     * {alias|kanonischer Code (normalisiert) => kanonischer Code}.
     *
     * @param  array<string, string>  $saved
     * @return array<string, string>
     */
    private static function aliasMap(EntitySpec $spec, array $saved): array {
        $aliases = [];
        foreach ($spec->headerAliases() as $alias => $canonical) {
            $aliases[self::normKey($alias)] = $canonical;
        }
        foreach ($spec->columns() as $col) {
            $aliases[self::normKey($col)] = $col;
        }
        // Gespeicherte Zuordnungen ergänzen nur: die Spec geht vor, und eine
        // Spalte, die es nicht mehr gibt, fällt still heraus.
        foreach ($saved as $header => $column) {
            if (in_array($column, $spec->columns(), true)) {
                $aliases += [self::normKey($header) => $column];
            }
        }

        return $aliases;
    }

    private static function normKey(string $value): string {
        return mb_strtolower(trim($value));
    }
}
