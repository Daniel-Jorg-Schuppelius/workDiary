<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawCsvParseRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „CSV übers Toolkit" (Vollscan 2026-08-23, C5): 2026-08-13
 * entstanden wieder zwei handgestrickte CSV-Reader (preg_split + str_getcsv,
 * fgetcsv hart auf `;`), obwohl `CsvFacade`/`CSVDocumentParser` Delimiter-
 * Erkennung, BOM, Quoting und mehrzeilige Felder abdecken. str_getcsv/fgetcsv
 * sind im App-Code tabu — Ausnahmen mit Begründung in die ALLOW_LIST.
 */
class RawCsvParseRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung */
    private const ALLOW_LIST = [];

    public function test_csv_parsing_goes_through_the_toolkit(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match('/\b(str_getcsv|fgetcsv)\s*\(/', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d — %s()', $relative, $this->lineOf($source, (int) $m[0][1]), $m[1][0]);
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Handgestrickter CSV-Parser — CsvFacade::streamAssoc/parseRows bzw. CSVDocumentParser\n"
            . "(detectDelimiter/readHeader/streamRows) nutzen; begründete Ausnahmen in die ALLOW_LIST.\n\n" . implode("\n", $violations));
    }
}
