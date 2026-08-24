<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvReaderRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate gegen handgestrickte CSV-Reader (Vollscan 2026-08-23, C5):
 * str_getcsv()/fgetcsv() auf Roh-Zeilen zerreißen gequotete Felder mit
 * Zeilenumbruch, kennen weder BOM noch Encoding-Erkennung — genau das Muster,
 * das B15 (TaxRuleController) bereits migriert hatte und das am 2026-08-13 in
 * zwei neuen Stellen wieder entstand. Toolkit-Weg: CsvFacade::streamAssoc()/
 * parseRows() bzw. CSVDocumentParser (inkl. detectDelimiter).
 */
class CsvReaderRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung / Nachzieh-Welle */
    private const ALLOW_LIST = [
        'app/Http/Controllers/OrgMemberController.php' => 'Welle 3 (C5): Mitglieder-Import auf CsvFacade::streamAssoc.',
        'app/Console/Commands/TimeExport/ImportExternalWageItemsCommand.php' => 'Welle 3 (C5): Lohnarten-Import auf CsvFacade::streamAssoc.',
        'app/Services/Isms/CsafFeedService.php' => 'Welle 3 (C5, optional): CSAF changes.csv, kleine Feed-Datei.',
        'app/Support/Toolkit/CsvFacade.php' => 'Die Fassade selbst darf die Primitive kapseln.',
    ];

    public function test_csv_is_read_through_the_toolkit(): void {
        $violations = [];

        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all('/(?<![\w$>:\\\\])(str_getcsv|fgetcsv)\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[1] as [$function, $offset]) {
                $violations[] = sprintf('%s:%d — %s()', $relative, $this->lineOf($source, (int) $offset), $function);
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Roher CSV-Reader gefunden — App\\Support\\Toolkit\\CsvFacade::streamAssoc()/parseRows()\n"
            . "(common-toolkit CSVDocumentParser: Quoting, BOM, Encoding, Delimiter-Erkennung) verwenden.\n\n"
            . implode("\n", $violations));
    }
}
