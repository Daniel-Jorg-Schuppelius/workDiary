<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvFacadeGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support;

use App\Support\Toolkit\CsvFacade;
use PHPUnit\Framework\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (csvformula-1): Compliance-Nachweise und das
 * Monatsabschluss-Bündel liefen an `CsvExport::guardRow()` vorbei. Reines
 * Quoting entschärft keine Formel — Excel wertet `=`, `+`, `-` und `@` beim
 * Öffnen aus.
 */
final class CsvFacadeGuardTest extends TestCase {
    public function test_build_csv_disarms_formulas_by_default(): void {
        $csv = CsvFacade::buildCsv(
            ['name', 'wert'],
            [['name' => '=SUM(A1:A9)', 'wert' => '@cmd']],
        );

        $this->assertStringNotContainsString('"=SUM', $csv);
        $this->assertStringContainsString("'=SUM(A1:A9)", $csv);
        $this->assertStringContainsString("'@cmd", $csv);
    }

    /** Echte Zahlen und Minusbeträge bleiben Zahlwerte (Lohn-/Buchhaltungsimport). */
    public function test_build_csv_keeps_negative_amounts_usable(): void {
        $csv = CsvFacade::buildCsv(['betrag'], [['betrag' => '-500.00']]);

        $this->assertStringContainsString('-500.00', $csv);
        $this->assertStringNotContainsString("'-500.00", $csv);
    }

    /** Maschinenformate (DATEV, GoBD) schalten den Guard ausdrücklich ab. */
    public function test_guard_can_be_switched_off_for_machine_formats(): void {
        $csv = CsvFacade::buildCsv(['name'], [['name' => '=SUM(A1:A9)']], guardFormulas: false);

        $this->assertStringContainsString('=SUM(A1:A9)', $csv);
        $this->assertStringNotContainsString("'=SUM", $csv);
    }
}
