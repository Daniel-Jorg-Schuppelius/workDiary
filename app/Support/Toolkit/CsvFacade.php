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

use CommonToolkit\Builders\CSVDocumentBuilder;
use CommonToolkit\Contracts\Interfaces\CSV\FieldInterface;
use CommonToolkit\Entities\CSV\{DataField, DataLine, HeaderField, HeaderLine};
use CommonToolkit\Parsers\CSVDocumentParser;
use Generator;

/**
 * Wrapper um CSV-Parser und -Builder des Toolkits.
 *
 * Bevorzugte API für CSV-Operationen im App-Code: liefert assoziative Zeilen
 * (Header zu Wert), kapselt Delimiter-Erkennung und Encoding.
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
     * Baut eine CSV-String-Repraesentation aus Header und Zeilen.
     *
     * @param  list<string>  $headers
     * @param  list<array<string, scalar|null>>  $rows
     */
    public static function buildCsv(array $headers, array $rows, string $delimiter = ';', string $enclosure = '"'): string {
        $builder = new CSVDocumentBuilder($delimiter, $enclosure);

        $headerFields = array_map(static fn(string $h): HeaderField => new HeaderField($h, $enclosure), $headers);
        $builder->setHeader(new HeaderLine($headerFields, $delimiter, $enclosure));

        foreach ($rows as $row) {
            $fields = [];
            foreach ($headers as $key) {
                $fields[] = new DataField((string) ($row[$key] ?? ''), $enclosure);
            }
            $builder->addRow(new DataLine($fields, $delimiter, $enclosure));
        }

        return (string) $builder->build();
    }
}
