<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XlsxExport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\{Coordinate, DataType};
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Schmaler Wrapper um PhpSpreadsheet für CSV-ähnliche XLSX-Exporte.
 *
 * Erwartet $headers als Liste von Strings und $rows als iterable<int, array<int|string, mixed>>.
 * Numerische Werte werden als Number-Cells geschrieben, Floats mit DE-Format `#,##0.00`.
 */
final class XlsxExport {
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<int|float|string|null>>  $rows
     */
    public static function streamFromArray(string $filename, array $headers, iterable $rows): StreamedResponse {
        $callback = static function () use ($headers, $rows): void {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();

            $col = 1;
            foreach ($headers as $label) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $coord = $letter . '1';
                $sheet->setCellValue($coord, (string) $label);
                $sheet->getStyle($coord)->getFont()->setBold(true);
                $col++;
            }

            $rowNum = 2;
            foreach ($rows as $rowData) {
                $col = 1;
                foreach ($rowData as $value) {
                    $letter = Coordinate::stringFromColumnIndex($col);
                    $coord = $letter . $rowNum;
                    $cell = $sheet->getCell($coord);
                    if (is_int($value) || is_float($value)) {
                        $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
                        if (is_float($value)) {
                            $sheet->getStyle($coord)
                                ->getNumberFormat()
                                ->setFormatCode('#,##0.00');
                        }
                    } else {
                        $cell->setValueExplicit((string) ($value ?? ''), DataType::TYPE_STRING);
                    }
                    $col++;
                }
                $rowNum++;
            }

            $lastCol = max(1, count($headers));
            for ($i = 1; $i <= $lastCol; $i++) {
                $letter = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($letter)->setAutoSize(true);
            }

            $writer = new Xlsx($book);
            $writer->save('php://output');
            $book->disconnectWorksheets();
            unset($book);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => self::MIME,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
