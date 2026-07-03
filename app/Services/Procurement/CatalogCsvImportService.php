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
use CommonToolkit\Helper\Data\StringHelper;
use CommonToolkit\Parsers\CSVDocumentParser;
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

    /**
     * Normalisiert eine Dezimalzahl gemäß Dezimaltrenner der Quelle.
     *
     * Bewusst app-lokal (Klasse D, geprüft 2026-07): Der explizit konfigurierte
     * `source.decimal_separator` schlägt die Format-Heuristik von
     * `NumberHelper::normalizeDecimalString` — Parity-Check ergab Diffs, z.B.
     * "1,234" bei Separator "." → hier 1234 (Tausender), Toolkit 1.234.
     */
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
        // Feature 052: Encoding-Konvertierung + BOM-Strip über das Common-Toolkit
        // (mb/iconv-Fallback, alle BOM-Varianten) statt app-lokaler Helfer.
        $csv = StringHelper::convertToUtf8($csv, $source->encoding);
        $csv = StringHelper::stripBom($csv);
        if (trim($csv) === '') {
            return [];
        }

        // Feature 052: CSV über den Toolkit-Parser einlesen (logische Zeilen,
        // RFC-konformes Quoting inkl. eingebetteter Trennzeichen/Zeilenumbrüche,
        // Konsistenzprüfung) statt zeilenweisem str_getcsv.
        $delimiter = $source->delimiter !== '' ? $source->delimiter[0] : ';';
        $document = CSVDocumentParser::fromString($csv, $delimiter, '"', $source->has_header);

        // Spaltennamen aus der Kopfzeile bzw. synthetisch (col0..colN).
        $columns = $source->has_header
            ? array_map(static fn ($name): string => (string) $name, array_values($document->getColumnNames()))
            : null;

        $records = [];
        foreach ($document->getRows() as $row) {
            $values = array_map(static fn ($field): string => $field->getValue(), array_values($row->getFields()));
            $names = $columns ?? array_map(static fn (int $i): string => 'col' . $i, array_keys($values));

            $record = [];
            foreach ($names as $i => $name) {
                $record[$name] = $values[$i] ?? '';
            }
            $records[] = $record;
        }

        return $records;
    }
}
