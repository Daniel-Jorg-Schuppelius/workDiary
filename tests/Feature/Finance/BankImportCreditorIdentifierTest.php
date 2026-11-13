<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankImportCreditorIdentifierTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\Finance\BankTransaction;
use App\Models\Platform\User;
use App\Services\Billing\FinancialFormatsSupport;
use App\Services\Finance\BankImportService;
use App\Support\Crypto\PurposeKeywordIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Gläubiger-ID und Zweck-Abdruck beim Import (Feature 207, MVP-1660): Die
 * Gläubiger-ID einer SEPA-Lastschrift wird übernommen, eine strukturierte
 * Gläubigerreferenz (RF…) nicht; der Verwendungszweck bleibt verschlüsselt,
 * daneben steht nur der geschlüsselte Abdruck seiner Stichwörter.
 */
class BankImportCreditorIdentifierTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        // Optionalpaket-Guard (AGENTS.md §9.1) — Muster PaymentReconciliationTest.
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $this->setUpOrganization();
        $this->actingAs(User::factory()->admin()->create(['organization_id' => $this->organization->id]));
    }

    public function test_camt_import_keeps_creditor_identifier_and_keyword_hash(): void {
        $file = new UploadedFile(base_path('tests/Fixtures/finance/camt053_direct_debit_sample.xml'), 'camt.xml', 'text/xml', null, true);
        app(BankImportService::class)->import($file, $this->organization->id);

        $debit = BankTransaction::query()->where('organization_id', $this->organization->id)->where('line_index', 0)->sole();
        $this->assertSame('DE98ZZZ09999999999', $debit->creditor_identifier);
        $this->assertSame('DEMO-MIETE-0001', $debit->mandate_ref);
        $this->assertSame(PurposeKeywordIndex::hash('Miete Büro Musterstraße 03/2026'), $debit->purpose_keyword_hash);
        $this->assertNotNull($debit->purpose_keyword_hash);

        $credit = BankTransaction::query()->where('organization_id', $this->organization->id)->where('line_index', 1)->sole();
        $this->assertNull($credit->creditor_identifier, 'RF-Referenz ist keine Gläubiger-ID');
    }
}
