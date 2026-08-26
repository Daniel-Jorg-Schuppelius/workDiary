<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CsvFormulaGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\User;
use App\Services\Isms\RegisterExportService;
use App\Support\CsvExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

/** Testfassade: macht csvWithMetadata für den Test aufrufbar. */
class ReportCsvHarness {
    use WritesReportCsv;

    /** @param list<list<string|int|float|null>> $rows */
    public function build(array $rows): Response {
        return $this->csvWithMetadata($rows, 'test.csv', 'test-report', []);
    }
}

/**
 * Vollreview W0.3: Formel-Injektions-Guard auf allen CSV-Pfaden, die
 * nutzergesteuerte Felder exportieren (Report-Trait, ISMS-Register).
 */
class CsvFormulaGuardTest extends TestCase {
    use RefreshDatabase;

    public function test_guard_prefixes_dangerous_cells_and_keeps_numbers(): void {
        $this->assertSame("'=SUM(A1:A9)", CsvExport::guard('=SUM(A1:A9)'));
        $this->assertSame("'+49 123", CsvExport::guard('+49 123'));
        $this->assertSame("'-2+3", CsvExport::guard('-2+3'));
        $this->assertSame("'@cmd", CsvExport::guard('@cmd'));
        $this->assertSame('harmlos', CsvExport::guard('harmlos'));
        $this->assertSame(-12.5, CsvExport::guard(-12.5));
        // MVP-729: negative Geldbeträge kommen als exakter Dezimalstring
        // (float ist bei Geld verboten) und dürfen kein Apostroph bekommen —
        // sonst ist die Provisions-Rückrechnung im Lohnimport kein Zahlwert.
        $this->assertSame('-500.00', CsvExport::guard('-500.00'));
        $this->assertSame('-12,50', CsvExport::guard('-12,50'));
        $this->assertSame(42, CsvExport::guard(42));
        $this->assertNull(CsvExport::guard(null));
    }

    public function test_report_csv_trait_guards_user_controlled_cells(): void {
        $response = (new ReportCsvHarness())->build([
            ['Kunde', 'Minuten'],
            ['=HYPERLINK("https://evil.example";"Klick")', 90],
        ]);

        $csv = (string) $response->getContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
    }

    public function test_isms_register_export_guards_user_controlled_cells(): void {
        $actor = User::factory()->create();

        $csv = (new RegisterExportService())->toCsv('risks', $actor, null, [
            'columns' => ['title' => 'Titel', 'owner' => 'Verantwortlich'],
            'rows' => [
                ['title' => '=CMD|calc!A1', 'owner' => 'Alice'],
            ],
        ]);

        $this->assertStringContainsString("'=CMD", $csv);
        $this->assertStringNotContainsString(";=CMD", $csv);
    }
}
