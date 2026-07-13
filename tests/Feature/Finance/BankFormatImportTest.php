<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankFormatImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BalanceCheck, BankStatementFormat, MatchStatus, TransactionDirection};
use App\Models\{Customer, Invoice, User};
use App\Services\Finance\{BankImportException, BankImportService, FinancialFormatsSupport, MatchingService};
use App\Services\Finance\Banking\BankStatementParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Allgemeiner Finanzformat-Import (MVP-334, Bauturbo A15): OFX/QIF/QXF und
 * PAIN.001/008 münden in DASSELBE interne Transaktionsschema wie CAMT/MT940 —
 * inhaltsbasierte Formaterkennung, identische Dedup-/Idempotenzregeln,
 * Matching-Pipeline unverändert nutzbar.
 */
class BankFormatImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    private function fixtureFile(string $name, string $mime = 'text/plain'): UploadedFile {
        return new UploadedFile(base_path('tests/Fixtures/finance/' . $name), $name, $mime, null, true);
    }

    private function importService(): BankImportService {
        return app(BankImportService::class);
    }

    public function test_format_detection_is_content_based(): void {
        $expectations = [
            'camt053_sample.xml' => BankStatementFormat::Camt053,
            'mt940_sample.sta' => BankStatementFormat::Mt940,
            'ofx_sample.ofx' => BankStatementFormat::Ofx,
            'qif_sample.qif' => BankStatementFormat::Qif,
            'qxf_sample.qxf' => BankStatementFormat::Qxf,
            'pain001_sample.xml' => BankStatementFormat::Pain001,
            'pain008_sample.xml' => BankStatementFormat::Pain008,
        ];

        foreach ($expectations as $file => $expected) {
            $content = (string) file_get_contents(base_path('tests/Fixtures/finance/' . $file));
            $this->assertSame($expected, BankStatementParser::detectFormat($content), $file);
        }
    }

    public function test_ofx_import_creates_same_internal_schema(): void {
        $statements = $this->importService()->import($this->fixtureFile('ofx_sample.ofx'), $this->organization->id);

        $this->assertCount(1, $statements);
        $statement = $statements[0];
        $this->assertSame(BankStatementFormat::Ofx, $statement->source_format);
        $this->assertSame(2, $statement->tx_count);
        // OFX liefert nur den Schlusssaldo (LEDGERBAL) — Saldenkette unvollständig.
        $this->assertSame(BalanceCheck::Unknown, $statement->balance_check);
        $this->assertSame('1069.00', $statement->closing_balance);

        $credit = $statement->transactions()->where('direction', TransactionDirection::Credit->value)->firstOrFail();
        $this->assertSame('119.00', $credit->amount);
        $this->assertSame('2026-05-15', $credit->booking_date->toDateString());
        $this->assertSame('Muster GmbH', $credit->counterparty_name);
        $this->assertContains('RE-2026-0007', $credit->extracted_refs);
        $this->assertSame(MatchStatus::Unmatched, $credit->match_status);

        $debit = $statement->transactions()->where('direction', TransactionDirection::Debit->value)->firstOrFail();
        $this->assertSame('50.00', $debit->amount);
    }

    public function test_ofx_transaction_feeds_existing_matching_pipeline(): void {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'RE-2026-0007',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-05-01',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ]);

        $statements = $this->importService()->import($this->fixtureFile('ofx_sample.ofx'), $this->organization->id);
        $tx = $statements[0]->transactions()->where('direction', TransactionDirection::Credit->value)->firstOrFail();

        $suggestions = app(MatchingService::class)->suggestFor($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertTrue($suggestions[0]['target']->is($invoice));
        $this->assertContains('reference', $suggestions[0]['reasons']);
        $this->assertContains('amount', $suggestions[0]['reasons']);
    }

    public function test_qif_import_uses_fallback_currency_and_same_schema(): void {
        $statements = $this->importService()->import($this->fixtureFile('qif_sample.qif'), $this->organization->id);

        $this->assertCount(1, $statements);
        $statement = $statements[0];
        $this->assertSame(BankStatementFormat::Qif, $statement->source_format);
        $this->assertSame(2, $statement->tx_count);
        $this->assertSame(BalanceCheck::Unknown, $statement->balance_check);

        $credit = $statement->transactions()->where('direction', TransactionDirection::Credit->value)->firstOrFail();
        $this->assertSame('119.00', $credit->amount);
        $this->assertSame('EUR', $credit->currency->value);
        $this->assertContains('RE-2026-0007', $credit->extracted_refs);
    }

    public function test_qxf_import_creates_transactions(): void {
        $statements = $this->importService()->import($this->fixtureFile('qxf_sample.qxf'), $this->organization->id);

        $this->assertSame(BankStatementFormat::Qxf, $statements[0]->source_format);
        $this->assertSame(2, $statements[0]->tx_count);
        $credit = $statements[0]->transactions()->where('direction', TransactionDirection::Credit->value)->firstOrFail();
        $this->assertContains('RE-2026-0007', $credit->extracted_refs);
    }

    public function test_pain001_import_creates_announced_debits(): void {
        $statements = $this->importService()->import($this->fixtureFile('pain001_sample.xml', 'text/xml'), $this->organization->id);

        $statement = $statements[0];
        $this->assertSame(BankStatementFormat::Pain001, $statement->source_format);
        $this->assertSame(1, $statement->tx_count);

        $tx = $statement->transactions()->firstOrFail();
        // Eigene Überweisung = Geld verlässt das Konto ⇒ debit.
        $this->assertSame(TransactionDirection::Debit, $tx->direction);
        $this->assertSame('250.00', $tx->amount);
        $this->assertSame('2026-05-18', $tx->booking_date->toDateString());
        $this->assertSame('ER-2026-0042', $tx->end_to_end_id);
        $this->assertSame('Lieferant AG', $tx->counterparty_name);
        $this->assertNotNull($tx->counterparty_iban_hash);
    }

    public function test_pain008_import_creates_announced_credits_with_mandate(): void {
        $statements = $this->importService()->import($this->fixtureFile('pain008_sample.xml', 'text/xml'), $this->organization->id);

        $statement = $statements[0];
        $this->assertSame(BankStatementFormat::Pain008, $statement->source_format);

        $tx = $statement->transactions()->firstOrFail();
        // Eigener Lastschrift-Einzug = Geldeingang ⇒ credit.
        $this->assertSame(TransactionDirection::Credit, $tx->direction);
        $this->assertSame('119.00', $tx->amount);
        $this->assertSame('2026-05-20', $tx->booking_date->toDateString());
        $this->assertSame('RE-2026-0007', $tx->end_to_end_id);
        $this->assertSame('MANDATE-0815', $tx->mandate_ref);
        $this->assertContains('RE-2026-0007', $tx->extracted_refs);
    }

    public function test_reimport_of_same_ofx_file_is_rejected_as_duplicate(): void {
        $this->importService()->import($this->fixtureFile('ofx_sample.ofx'), $this->organization->id);

        $this->expectException(BankImportException::class);
        $this->importService()->import($this->fixtureFile('ofx_sample.ofx'), $this->organization->id);
    }

    public function test_same_file_is_importable_for_another_organization(): void {
        // Datei-Hash-Dublette ist org-scoped — eine fremde Organisation darf
        // dieselbe Datei importieren (Mandantentrennung der Dedup-Regel).
        $this->importService()->import($this->fixtureFile('qif_sample.qif'), $this->organization->id);

        $other = \App\Models\Organization::factory()->create();
        $statements = $this->importService()->import($this->fixtureFile('qif_sample.qif'), $other->id);

        $this->assertCount(1, $statements);
        $this->assertSame((int) $other->id, (int) $statements[0]->organization_id);
    }
}
