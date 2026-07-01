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
use CommonToolkit\Entities\CSV\DataLine;
use CommonToolkit\Helper\Data\CSV\StringHelper;
use CommonToolkit\Parsers\CSVDocumentParser;
use Generator;

/**
 * Aggregations-API für die zusammengesetzten CSV-Operationen im App-Code
 * (Lesen: Delimiter-Erkennung, Encoding, logische Zeilen). Delegiert
 * durchgehend an das php-common-toolkit ({@see CSVDocumentParser} zum Parsen,
 * {@see StringHelper::encodeLine} zum Serialisieren). Einzelne Ausgabezeilen
 * nutzen den Toolkit-Encoder direkt an der Aufrufstelle; hier bleibt nur die
 * zusammengesetzte {@see buildCsv}.
 */
final class CsvFacade {
    public static function detectDelimiter(string $file, int $maxLines = 10): string {
        return CSVDocumentParser::detectDelimiter($file, $maxLines);
    }

    /**
     * @return list<string>
     */
    public static function readHeader(string $file, ?string $delimiter = null): array {
        $delimiter ??= self::detectDelimiter($file);

        return array_values(CSVDocumentParser::readHeader($file, $delimiter)->getColumnNames());
    }

    /**
     * Streamt Datenzeilen als DataLine-Objekte.
     *
     * @return Generator<int, DataLine>
     */
    public static function streamRows(
        string $file,
        ?string $delimiter = null,
        string $enclosure = FieldInterface::DEFAULT_ENCLOSURE,
        bool $hasHeader = true,
    ): Generator {
        $delimiter ??= self::detectDelimiter($file);

        return CSVDocumentParser::streamRows($file, $delimiter, $enclosure, $hasHeader);
    }

    /**
     * Streamt Datenzeilen als assoziative Arrays.
     *
     * @return Generator<int, array<string, string>>
     */
    public static function streamAssoc(
        string $file,
        ?string $delimiter = null,
        string $enclosure = FieldInterface::DEFAULT_ENCLOSURE,
    ): Generator {
        $delimiter ??= self::detectDelimiter($file);
        $columns = self::readHeader($file, $delimiter);
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
