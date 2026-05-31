<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XlsxExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support;

use App\Support\XlsxExport;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

final class XlsxExportTest extends TestCase {
    public function test_streams_xlsx_with_headers_and_rows(): void {
        $response = XlsxExport::streamFromArray(
            'unit-test.xlsx',
            ['Name', 'Minuten', 'Erloes'],
            [
                ['Alice', 120, 99.5],
                ['Bob', 45, 12.0],
            ],
        );

        $this->assertSame(XlsxExport::MIME, $response->headers->get('Content-Type'));
        $this->assertStringContainsString('unit-test.xlsx', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();
        $this->assertGreaterThan(100, strlen($body));

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $this->assertIsString($tmp);
        ToolkitFile::write($tmp, $body);

        $book = IOFactory::load($tmp);
        $sheet = $book->getActiveSheet();
        $this->assertSame('Name', $sheet->getCell('A1')->getValue());
        $this->assertSame('Minuten', $sheet->getCell('B1')->getValue());
        $this->assertSame('Erloes', $sheet->getCell('C1')->getValue());
        $this->assertSame('Alice', $sheet->getCell('A2')->getValue());
        $this->assertEquals(120, $sheet->getCell('B2')->getValue());
        $this->assertEqualsWithDelta(99.5, (float) $sheet->getCell('C2')->getValue(), 0.001);
        $this->assertSame('Bob', $sheet->getCell('A3')->getValue());
        $this->assertEquals(45, $sheet->getCell('B3')->getValue());
        $this->assertEqualsWithDelta(12.0, (float) $sheet->getCell('C3')->getValue(), 0.001);

        @unlink($tmp);
    }
}
