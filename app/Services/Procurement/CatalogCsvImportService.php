<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogCsvImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\SupplierCatalogSource;
use RuntimeException;

/**
 * Importiert eine Lieferanten-CSV gemäß konfigurierbarem Mapping in die
 * Katalogartikel einer Quelle (Feature 050, MVP-092/094). Parst und mappt die
 * CSV zu normalisierten Datensätzen und übergibt sie dem {@see CatalogItemUpserter}
 * (gemeinsame Persistenz mit Hash-Änderungserkennung, Preis-Historie,
 * Abkündigung und Margenwarnung).
 */
class CatalogCsvImportService {
    /** Pflicht-Zielfelder im Mapping. */
    private const REQUIRED = ['external_no', 'name'];

    /** Felder, die direkt am Katalogartikel gespeichert werden. */
    private const FIELDS = [
        'external_no', 'manufacturer_no', 'manufacturer', 'brand', 'gtin', 'category',
        'classification_system', 'classification_code',
        'name', 'description', 'product_url', 'image_url', 'datasheet_url', 'purchase_price', 'currency',
        'pack_size', 'base_qty', 'availability', 'lead_time_days',
    ];

    private const DECIMAL_FIELDS = ['purchase_price', 'pack_size', 'base_qty'];

    public function __construct(private readonly CatalogItemUpserter $upserter = new CatalogItemUpserter()) {}

    /**
     * @param  array<string, string>  $mapping  Zielfeld => CSV-Spaltenname
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     *
     * @throws RuntimeException Bei fehlendem Pflichtmapping oder nicht passendem Header (Preflight).
     */
    public function import(SupplierCatalogSource $source, string $csv, array $mapping): array {
        foreach (self::REQUIRED as $field) {
            if (empty($mapping[$field])) {
                throw new RuntimeException((string) __('procurement.catalog.error.mapping_required', ['field' => $field]));
            }
        }

        $rows = $this->parse($source, $csv);
        if ($rows === []) {
            throw new RuntimeException((string) __('procurement.catalog.error.empty_file'));
        }

        $header = array_keys($rows[0]);
        foreach (self::REQUIRED as $field) {
            if (! in_array($mapping[$field], $header, true)) {
                throw new RuntimeException((string) __('procurement.catalog.error.header_mismatch', ['column' => $mapping[$field]]));
            }
        }

        $records = array_map(fn (array $row): array => $this->extract($source, $row, $mapping), $rows);

        return $this->upserter->persist($source, $records, $csv);
    }

    /**
     * @param  array<string, string>  $row      Spaltenname => Wert
     * @param  array<string, string>  $mapping  Zielfeld => Spaltenname
     * @return array<string, mixed>
     */
    private function extract(SupplierCatalogSource $source, array $row, array $mapping): array {
        $values = [];
        foreach (self::FIELDS as $field) {
            $column = $mapping[$field] ?? null;
            if ($column === null || ! array_key_exists($column, $row)) {
                continue;
            }
            $raw = trim((string) $row[$column]);

            if (in_array($field, self::DECIMAL_FIELDS, true)) {
                $values[$field] = $raw === '' ? null : $this->decimal($raw, $source->decimal_separator);
            } elseif ($field === 'lead_time_days') {
                $values[$field] = $raw === '' ? null : (int) $raw;
            } else {
                $values[$field] = $raw;
            }
        }

        return $values;
    }

    /** Normalisiert eine Dezimalzahl gemäß Dezimaltrenner der Quelle. */
    private function decimal(string $raw, string $separator): ?string {
        $normalized = $separator === ','
            ? str_replace(['.', ','], ['', '.'], $raw)  // 1.234,56 → 1234.56
            : str_replace(',', '', $raw);               // 1,234.56 → 1234.56

        return is_numeric($normalized) ? $normalized : null;
    }

    /**
     * Parst die CSV in eine Liste assoziativer Zeilen (Spaltenname => Wert).
     *
     * @return list<array<string, string>>
     */
    private function parse(SupplierCatalogSource $source, string $csv): array {
        if (strtoupper($source->encoding) !== 'UTF-8') {
            $converted = @mb_convert_encoding($csv, 'UTF-8', $source->encoding);
            if (is_string($converted)) {
                $csv = $converted;
            }
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv; // BOM entfernen

        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
        if ($lines === []) {
            return [];
        }

        $delimiter = $source->delimiter !== '' ? $source->delimiter[0] : ';';
        $header = $source->has_header
            ? array_map(fn ($v): string => trim((string) $v), str_getcsv(array_shift($lines), $delimiter))
            : array_map(fn (int $i): string => 'col' . $i, range(0, count(str_getcsv($lines[0], $delimiter)) - 1));

        $records = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line, $delimiter);
            $record = [];
            foreach ($header as $i => $name) {
                $record[$name] = isset($cells[$i]) ? (string) $cells[$i] : '';
            }
            $records[] = $record;
        }

        return $records;
    }
}
