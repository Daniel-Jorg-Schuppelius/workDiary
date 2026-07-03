<?php
/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvFacade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Toolkit;

use CommonToolkit\Contracts\Interfaces\CSV\FieldInterface;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use CommonToolkit\Parsers\CSVDocumentParser;
use Generator;

/**
 * Zusammengesetzte CSV-Operationen im App-Code, die mehrere Toolkit-Aufrufe
 * kombinieren ({@see CSVDocumentParser} zum Parsen, {@see StringHelper::encodeLine}
 * zum Serialisieren). Reine 1:1-Delegationen an das Toolkit gehören nicht
 * hierher — direkte Toolkit-Aufrufe an der Aufrufstelle nutzen.
 */
final class CsvFacade {
    /**
     * Streamt Datenzeilen als assoziative Arrays (Header-Spalten als Keys).
     *
     * @return Generator<int, array<string, string>>
     */
    public static function streamAssoc(
        string $file,
        ?string $delimiter = null,
        string $enclosure = FieldInterface::DEFAULT_ENCLOSURE,
    ): Generator {
        $delimiter ??= CSVDocumentParser::detectDelimiter($file);
        $columns = array_values(CSVDocumentParser::readHeader($file, $delimiter)->getColumnNames());
        $columnCount = count($columns);

        foreach (CSVDocumentParser::streamRows($file, $delimiter, $enclosure, true) as $lineNumber => $row) {
            $assoc = [];
            $fields = $row->getFields();
            for ($i = 0; $i < $columnCount; $i++) {
                $assoc[$columns[$i]] = isset($fields[$i]) ? $fields[$i]->getValue() : '';
            }
            yield $lineNumber => $assoc;
        }
    }

    /**
     * Baut eine CSV-String-Repräsentation aus Header und Zeilen (mit \r\n-Zeilen).
     *
     * @param  list<string>  $headers
     * @param  list<array<string, scalar|null>>  $rows
     */
    public static function buildCsv(array $headers, array $rows, string $delimiter = ';', string $enclosure = '"'): string {
        $out = StringHelper::encodeLine($headers, $delimiter, $enclosure) . "\r\n";

        foreach ($rows as $row) {
            $cells = array_map(static fn(string $key): mixed => $row[$key] ?? '', $headers);
            $out .= StringHelper::encodeLine($cells, $delimiter, $enclosure) . "\r\n";
        }

        return $out;
    }

    /**
     * Parst einen CSV-String zu positionellen Zeilen inklusive Kopfzeile.
     * Pendant zu {@see streamRows()} für bereits im Speicher liegende Inhalte.
     *
     * @return list<list<string>>
     */
    public static function parseRows(string $csv, string $delimiter = ',', string $enclosure = FieldInterface::DEFAULT_ENCLOSURE): array {
        $rows = [];
        foreach (CSVDocumentParser::fromString($csv, $delimiter, $enclosure, false)->getRows() as $line) {
            $rows[] = array_values(array_map(static fn($field): string => (string) $field->getValue(), $line->getFields()));
        }

        return $rows;
    }
}
