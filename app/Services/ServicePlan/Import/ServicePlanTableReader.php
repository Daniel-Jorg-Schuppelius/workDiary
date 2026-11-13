<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanTableReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Import;

use CommonToolkit\Entities\XLSX\Cell;
use CommonToolkit\Parsers\{CSVDocumentParser, XLSXDocumentParser};
use RuntimeException;
use Throwable;

/**
 * Liest eine hochgeladene Liste (CSV oder Excel, erstes Blatt) über die Parser
 * des common-toolkit als Kopfzeile + Datenzeilen (Feature 199, MVP-1640).
 * CSV: Trennzeichen und Zeichensatz erkennt das Toolkit (DATEV-Exporte kommen
 * oft in Windows-1252). Eine DATEV-Kennzeile (`EXTF`/`DTVF`) vor der
 * Kopfzeile wird übersprungen. Leere Zeilen entfallen.
 */
final class ServicePlanTableReader {
    /** Höchstzahl der Datenzeilen je Datei. */
    public const int MAX_ROWS = 5000;

    /**
     * @return array{header: list<string>, rows: array<int, list<string>>} Datenzeilen mit Zeilennummer der Datei (1 = Kopfzeile)
     *
     * @throws RuntimeException bei unlesbarer oder leerer Datei
     */
    public function read(string $path, string $extension): array {
        try {
            $lines = strtolower($extension) === 'xlsx' ? $this->xlsx($path) : $this->csv($path);
        } catch (Throwable $e) {
            throw new RuntimeException((string) __('service_plan.import.error.unreadable'), 0, $e);
        }

        $header = null;
        $rows = [];
        foreach ($lines as $number => $cells) {
            $cells = array_map(static fn (mixed $cell): string => trim((string) $cell), $cells);
            if (implode('', $cells) === '') {
                continue;
            }
            if ($header === null) {
                if (in_array(strtoupper($cells[0]), ['EXTF', 'DTVF'], true)) {
                    continue;
                }
                $header = $cells;

                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException((string) __('service_plan.import.error.too_many', ['max' => self::MAX_ROWS]));
            }
            $rows[$number] = $cells;
        }
        if ($header === null || $rows === []) {
            throw new RuntimeException((string) __('service_plan.import.error.empty'));
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /** @return array<int, list<string>> Zeilennummer → Zellen */
    private function csv(string $path): array {
        $lines = [];
        $number = 0;
        foreach (CSVDocumentParser::streamRows($path, CSVDocumentParser::detectDelimiter($path), hasHeader: false) as $line) {
            $lines[++$number] = array_values(array_map(static fn ($field): string => (string) $field->getValue(), $line->getFields()));
        }

        return $lines;
    }

    /** @return array<int, list<string>> Zeilennummer → Zellen (Datum als `Y-m-d`) */
    private function xlsx(string $path): array {
        $sheet = XLSXDocumentParser::fromFile($path, hasHeader: false, sheetIndex: 0)->getFirstSheet();
        $lines = [];
        $number = 0;
        foreach ($sheet?->getRows() ?? [] as $row) {
            $lines[++$number] = array_values(array_map(static fn (Cell $cell): string => $cell->toCanonicalString(), $row->getCells()));
        }

        return $lines;
    }
}
