<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentReturnTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AllocationKind, MatchStatus, TransactionDirection};
use App\Models\{Customer, Invoice, Organization, User};
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Services\Finance\{BankImportException, BankImportService, FinancialFormatsSupport, MatchingService, ReconciliationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zahlungsabgleich-Rest (MVP-334, Bauturbo A15): Sammelbuchungs-Auflösung
 * (mehrere Zuordnungen mit Teilbeträgen je Umsatz) und Lastschrift-Rückläufer-
 * Workflow (GoBD-konforme Kompensation — Original bleibt Historie, offener
 * Posten wird wieder geöffnet, Grund dokumentiert).
 */
class PaymentReturnTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
    }

    private function fixtureFile(string $name): UploadedFile {
        return new UploadedFile(base_path('tests/Fixtures/finance/' . $name), $name, 'text/xml', null, true);
    }

    private function makeInvoice(string $number, string $total = '119.00'): Invoice {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-05-01',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => $total,
            'created_by' => $this->admin->id,
        ]);
    }

    private function makeTransaction(string $amount, TransactionDirection $direction = TransactionDirection::Credit): BankTransaction {
        return BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->organization->id])->id,
            'amount' => $amount,
            'direction' => $direction,
            'match_status' => MatchStatus::Unmatched,
        ]);
    }

    public function test_collective_transaction_splits_into_multiple_allocations(): void {
        // Sammelbuchung: EIN Umsatz deckt zwei offene Rechnungen (Teilbeträge).
        $first = $this->makeInvoice('RE-2026-0101');
        $second = $this->makeInvoice('RE-2026-0102', '100.00');
        $tx = $this->makeTransaction('219.00');

        app(ReconciliationService::class)->confirm($tx, [
            ['type' => Invoice::class, 'id' => $first->id, 'amount' => 119.00],
            ['type' => Invoice::class, 'id' => $second->id, 'amount' => 100.00],
        ]);

        $this->assertSame(Invoice::STATUS_PAID, $first->refresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $second->refresh()->status);
        $this->assertSame(MatchStatus::Matched, $tx->refresh()->match_status);
        $this->assertSame(2, $tx->allocations()->count());
    }

    public function test_return_import_persists_iso_return_reason(): void {
        $statements = app(BankImportService::class)->import($this->fixtureFile('camt053_return_sample.xml'), $this->organization->id);

        $tx = $statements[0]->transactions()->firstOrFail();
        $this->assertSame(TransactionDirection::Debit, $tx->direction);
        $this->assertSame('AC04', $tx->return_reason);
        $this->assertTrue($tx->isReturnCandidate());
        $this->assertSame('MANDATE-0815', $tx->mandate_ref);
    }

    public function test_process_return_compensates_original_and_reopens_invoice(): void {
        $invoice = $this->makeInvoice('RE-2026-0007');

        // Ursprüngliche Zahlung bestätigen (Rechnung bezahlt).
        $statements = app(BankImportService::class)->import($this->fixtureFile('camt053_sample.xml'), $this->organization->id);
        $paymentTx = $statements[0]->transactions()->firstOrFail();
        app(ReconciliationService::class)->confirm($paymentTx, [[
            'type' => Invoice::class,
            'id' => $invoice->id,
            'amount' => 119.00,
        ]]);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
        $original = PaymentAllocation::query()->where('bank_transaction_id', $paymentTx->id)->firstOrFail();

        // Rückläufer importieren; Kandidaten-Vorschlag findet die Original-Zuordnung.
        $returnStatements = app(BankImportService::class)->import($this->fixtureFile('camt053_return_sample.xml'), $this->organization->id);
        $returnTx = $returnStatements[0]->transactions()->firstOrFail();
        $origins = app(MatchingService::class)->suggestReturnOrigins($returnTx);
        $this->assertNotEmpty($origins);
        $this->assertTrue($origins[0]['allocation']->is($original));
        $this->assertContains('amount', $origins[0]['reasons']);
        $this->assertContains('reference', $origins[0]['reasons']);

        app(ReconciliationService::class)->processReturn($returnTx, $original);

        // GoBD: Original bleibt als AKTIVE Historie stehen — kompensiert wird
        // über eine negative Chargeback-Zuordnung am Rückläufer-Umsatz.
        $this->assertFalse($original->refresh()->trashed());
        $compensation = PaymentAllocation::query()
            ->where('bank_transaction_id', $returnTx->id)
            ->where('kind', AllocationKind::Chargeback->value)
            ->firstOrFail();
        $this->assertSame('-119.00', (string) $compensation->amount);
        $this->assertStringContainsString('AC04', (string) $compensation->note);

        // Offener Posten wieder offen, Rückläufer zugeordnet, Event geschrieben.
        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertNull($invoice->paid_on);
        $this->assertSame(MatchStatus::Matched, $returnTx->refresh()->match_status);
        $this->assertDatabaseHas('payment_reconciliation_events', [
            'bank_transaction_id' => $returnTx->id,
            'event' => 'return_processed',
        ]);
    }

    public function test_process_return_is_idempotent_per_original_allocation(): void {
        $invoice = $this->makeInvoice('RE-2026-0007');
        $paymentTx = $this->makeTransaction('119.00');
        app(ReconciliationService::class)->confirm($paymentTx, [[
            'type' => Invoice::class,
            'id' => $invoice->id,
            'amount' => 119.00,
        ]]);
        $original = PaymentAllocation::query()->where('bank_transaction_id', $paymentTx->id)->firstOrFail();

        $returnTx = $this->makeTransaction('119.00', TransactionDirection::Debit);
        app(ReconciliationService::class)->processReturn($returnTx, $original, 'AC04');

        $secondReturn = $this->makeTransaction('119.00', TransactionDirection::Debit);
        $this->expectException(BankImportException::class);
        app(ReconciliationService::class)->processReturn($secondReturn, $original, 'AC04');
    }

    public function test_process_return_rejects_cross_org_allocation(): void {
        $other = Organization::factory()->create();
        $foreignStatement = BankStatement::factory()->create(['organization_id' => $other->id]);
        $foreignTx = BankTransaction::factory()->create([
            'organization_id' => $other->id,
            'bank_statement_id' => $foreignStatement->id,
            'amount' => '119.00',
            'direction' => TransactionDirection::Credit,
        ]);
        $foreignAllocation = PaymentAllocation::factory()->create([
            'organization_id' => $other->id,
            'bank_transaction_id' => $foreignTx->id,
        ]);

        $returnTx = $this->makeTransaction('119.00', TransactionDirection::Debit);

        $this->expectException(BankImportException::class);
        app(ReconciliationService::class)->processReturn($returnTx, $foreignAllocation, 'AC04');
    }
}
