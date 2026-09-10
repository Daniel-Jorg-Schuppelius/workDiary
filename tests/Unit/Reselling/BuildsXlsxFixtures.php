<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildsXlsxFixtures.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\{Coordinate, DataType};
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * XLSX-Fixtures mit typisierten Zellen: `DateTimeInterface` wird eine echte
 * Datumszelle (Seriennummer + Datumsformat), Strings bleiben Text (auch
 * „1.200"), Zahlen werden Zahlzellen — anders als `XlsxExport`, das nur
 * Text/Zahl kennt. Damit lässt sich der Reader-Pfad für Excel-Datumszellen
 * prüfen (Review 2026-09-10, C3).
 */
trait BuildsXlsxFixtures {
    /**
     * @param  list<array{0: string, 1: list<list<int|float|string|DateTimeInterface|null>>}>  $sheets  [Blattname, Zeilen inkl. Kopfzeile]
     */
    private static function xlsxFixture(array $sheets, string $prefix = 'resale'): string {
        $book = new Spreadsheet;
        $first = true;
        foreach ($sheets as [$title, $rows]) {
            $sheet = $first ? $book->getActiveSheet() : $book->createSheet();
            $first = false;
            $sheet->setTitle($title);
            foreach ($rows as $rowOffset => $row) {
                foreach ($row as $columnOffset => $value) {
                    $coordinate = Coordinate::stringFromColumnIndex($columnOffset + 1) . ($rowOffset + 1);
                    if ($value instanceof DateTimeInterface) {
                        $sheet->setCellValue($coordinate, Date::PHPToExcel($value));
                        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('DD.MM.YYYY');
                    } elseif (is_string($value)) {
                        $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
                    } elseif ($value !== null) {
                        $sheet->setCellValue($coordinate, $value);
                    }
                }
            }
        }
        $path = sys_get_temp_dir() . '/' . $prefix . '-' . uniqid() . '.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
