<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support;

use App\Support\CsvExport;
use CommonToolkit\Helper\Data\StringHelper;
use PHPUnit\Framework\TestCase;

final class CsvExportTest extends TestCase {
    /**
     * @param  list<string>  $header
     * @param  iterable<int, list<int|float|bool|string|null>>  $rows
     */
    private function render(array $header, iterable $rows): string {
        $response = CsvExport::streamFromRows('unit-test.csv', $header, $rows);

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('unit-test.csv', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    public function test_writes_bom_header_and_semicolon_delimited_rows(): void {
        $body = $this->render(['Name', 'Ort'], [['Alice', 'Berlin']]);

        $this->assertStringStartsWith(StringHelper::BOM_UTF8, $body);
        $this->assertSame(
            StringHelper::BOM_UTF8 . "Name;Ort\r\nAlice;Berlin\r\n",
            $body,
        );
    }

    public function test_guards_formula_prefixes_in_string_cells(): void {
        $body = $this->render(['Wert'], [
            ['=SUM(A1:A9)'],
            ['+49 123'],
            ['-neg'],
            ['@handle'],
            ['  =lead'], // Guard prüft nach ltrim, Original bleibt erhalten
            [-5], // echte Zahl bleibt ungeguarded
            ['normal'],
        ]);

        $lines = explode("\r\n", substr($body, strlen(StringHelper::BOM_UTF8)));
        $this->assertSame("'=SUM(A1:A9)", $lines[1]);
        $this->assertSame("'+49 123", $lines[2]);
        $this->assertSame("'-neg", $lines[3]);
        $this->assertSame("'@handle", $lines[4]);
        $this->assertSame("'  =lead", $lines[5]);
        $this->assertSame('-5', $lines[6]);
        $this->assertSame('normal', $lines[7]);
    }

    public function test_header_row_stays_unguarded_and_umlauts_survive(): void {
        $body = $this->render(['Straße', '+/-'], [['Müller-Lüdenscheid', 'Größe Ü']]);

        $lines = explode("\r\n", substr($body, strlen(StringHelper::BOM_UTF8)));
        $this->assertSame('Straße;+/-', $lines[0]);
        // '-' mitten im Wort löst den Guard nicht aus, UTF-8 bleibt unangetastet
        $this->assertSame('Müller-Lüdenscheid;Größe Ü', $lines[1]);
    }

    public function test_accepts_generator_rows_and_encodes_delimiter_values(): void {
        $rows = (static function (): \Generator {
            yield ['a;b', null, 42];
        })();

        $body = $this->render(['Spalte', 'Leer', 'Zahl'], $rows);

        $lines = explode("\r\n", substr($body, strlen(StringHelper::BOM_UTF8)));
        // Feld mit Delimiter wird gequotet, null wird zu leerem Feld
        $this->assertSame('"a;b";;42', $lines[1]);
    }
}
