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
use CommonToolkit\Parsers\CSVDocumentParser;
use Generator;

/**
 * Bevorzugte API für CSV-Operationen im App-Code.
 *
 * Lesen delegiert an das php-common-toolkit ({@see CSVDocumentParser}): Delimiter-
 * Erkennung, Encoding, logische Zeilen. Schreiben übernimmt {@see line()} mit
 * RFC-4180-konformem Quoting — die Toolkit-Felder sind round-trip-orientiert und
 * quoten selbst erzeugte Werte mit Trennzeichen nicht, taugen also nicht als
 * Serializer. So bleibt CSV-Handling appweit an dieser Fassade gebündelt.
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
        $out = self::line($headers, $delimiter, $enclosure) . "\r\n";

        foreach ($rows as $row) {
            $cells = array_map(static fn(string $key): mixed => $row[$key] ?? '', $headers);
            $out .= self::line($cells, $delimiter, $enclosure) . "\r\n";
        }

        return $out;
    }

    /**
     * Rendert eine einzelne CSV-Zeile mit RFC-4180-Quoting, ohne abschließenden
     * Zeilenumbruch. Für streamende Exporte nach php://output. Ein Feld wird nur
     * gequotet, wenn es Trennzeichen, Anführungszeichen oder Zeilenumbruch enthält;
     * enthaltene Anführungszeichen werden verdoppelt.
     *
     * @param  array<int|string, scalar|null>  $cells
     */
    public static function line(array $cells, string $delimiter = ';', string $enclosure = FieldInterface::DEFAULT_ENCLOSURE): string {
        $rendered = array_map(
            static function ($value) use ($delimiter, $enclosure): string {
                $string = $value === null ? '' : (string) $value;
                if (
                    str_contains($string, $delimiter)
                    || str_contains($string, $enclosure)
                    || str_contains($string, "\n")
                    || str_contains($string, "\r")
                ) {
                    $string = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $string) . $enclosure;
                }

                return $string;
            },
            array_values($cells),
        );

        return implode($delimiter, $rendered);
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
