<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogXlsxImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\SupplierCatalogSource;
use App\Support\XlsxCellValue;
use CommonToolkit\Entities\XLSX\Cell;
use CommonToolkit\Parsers\XLSXDocumentParser;
use RuntimeException;
use Throwable;

/**
 * Importiert eine XLSX-Preisliste in die Katalogartikel einer Quelle
 * (Feature 050, MVP-541). Wählt das Tabellenblatt (`sheet_name`, leer =
 * erstes Blatt), normalisiert die Zeilen auf die CSV-Struktur und übergibt
 * an die gemeinsame Strecke {@see CatalogCsvImportService::importRows()}
 * (Preflight, Mapping, Persistenz mit Preis-Historie).
 */
class CatalogXlsxImportService {
    public function __construct(private readonly CatalogCsvImportService $csv = new CatalogCsvImportService()) {}

    /**
     * @param  array<string, string>  $mapping  Zielfeld => Spaltenname
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     *
     * @throws RuntimeException Bei unlesbarer Datei, unbekanntem Blatt oder Preflight-Fehlern.
     */
    public function import(SupplierCatalogSource $source, string $content, array $mapping): array {
        return $this->csv->importRows($source, $this->parse($source, $content), $mapping, $content);
    }

    /**
     * Der Toolkit-Parser liest nur Dateien — der Binär-Content läuft daher
     * über eine Temp-Datei (Präzedenz: CsvPreflightAnalyzer, A13).
     *
     * @return list<array<string, string>>
     */
    private function parse(SupplierCatalogSource $source, string $content): array {
        $path = tempnam(sys_get_temp_dir(), 'wd-xlsx-');
        if ($path === false) {
            throw new RuntimeException((string) __('procurement.catalog.error.xlsx_invalid'));
        }

        try {
            file_put_contents($path, $content);

            try {
                $document = XLSXDocumentParser::fromFile($path, $source->has_header);
            } catch (Throwable) {
                throw new RuntimeException((string) __('procurement.catalog.error.xlsx_invalid'));
            }

            $sheetName = trim((string) $source->sheet_name);
            $sheet = $sheetName !== '' ? $document->getSheetByName($sheetName) : $document->getFirstSheet();
            if ($sheet === null) {
                throw new RuntimeException((string) __('procurement.catalog.error.sheet_not_found', ['sheet' => $sheetName]));
            }

            // Spaltennamen aus der Kopfzeile bzw. synthetisch (col0..colN) —
            // gleiche Konvention wie der CSV-Pfad.
            $columns = $source->has_header
                ? array_map(static fn ($name): string => (string) $name, array_values($sheet->getHeaderNames()))
                : null;

            $records = [];
            foreach ($sheet->getRows() as $row) {
                $values = array_map(
                    static fn (Cell $cell): string => XlsxCellValue::toString($cell),
                    array_values($row->getCells()),
                );
                $names = $columns ?? array_map(static fn (int $i): string => 'col' . $i, array_keys($values));

                $record = [];
                foreach ($names as $i => $name) {
                    $record[$name] = $values[$i] ?? '';
                }
                $records[] = $record;
            }

            return $records;
        } finally {
            @unlink($path);
        }
    }
}
