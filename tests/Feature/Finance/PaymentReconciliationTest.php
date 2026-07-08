<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentReconciliationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AllocationKind, BalanceCheck, MatchStatus, TransactionDirection};
use App\Models\{Customer, Invoice, Organization, User};
use App\Models\Expense;
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Services\Finance\{BankImportException, BankImportService, MatchingService, ReconciliationService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zahlungsabgleich (Feature 045, Priorität 3 / Phase 4): Import, Matching,
 * Bestätigung, Reversibilität, Dubletten, Verschlüsselung, Mandantentrennung.
 */
class PaymentReconciliationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
    }

    private function camtFile(): UploadedFile {
        return new UploadedFile(base_path('tests/Fixtures/finance/camt053_sample.xml'), 'camt.xml', 'text/xml', null, true);
    }

    private function mt940File(): UploadedFile {
        return new UploadedFile(base_path('tests/Fixtures/finance/mt940_sample.sta'), 'mt940.sta', 'text/plain', null, true);
    }

    private function importService(): BankImportService {
        return app(BankImportService::class);
    }

    private function makeInvoice(string $number = 'RE-2026-0007', string $total = '119.00', string $status = Invoice::STATUS_ISSUED): Invoice {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => $status,
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

    public function test_camt_import_creates_statement_and_transactions(): void {
        $statements = $this->importService()->import($this->camtFile(), $this->organization->id);

        $this->assertCount(1, $statements);
        $statement = $statements[0];
        $this->assertSame(1, $statement->tx_count);
        $this->assertSame(BalanceCheck::Ok, $statement->balance_check);

        $tx = $statement->transactions()->first();
        $this->assertNotNull($tx);
        $this->assertSame(TransactionDirection::Credit, $tx->direction);
        $this->assertSame('119.00', $tx->amount);
        $this->assertContains('RE-2026-0007', $tx->extracted_refs);
        $this->assertSame(MatchStatus::Unmatched, $tx->match_status);
    }

    public function test_mt940_import_creates_statement(): void {
        $statements = $this->importService()->import($this->mt940File(), $this->organization->id);

        $this->assertCount(1, $statements);
        $this->assertSame(1, $statements[0]->tx_count);
        $this->assertSame(BalanceCheck::Ok, $statements[0]->balance_check);
    }

    public function test_reimport_same_file_is_rejected_as_duplicate(): void {
        $this->importService()->import($this->camtFile(), $this->organization->id);

        $this->expectException(BankImportException::class);
        $this->importService()->import($this->camtFile(), $this->organization->id);
    }

    public function test_suggestion_matches_invoice_by_number_and_amount(): void {
        $invoice = $this->makeInvoice();
        $statements = $this->importService()->import($this->camtFile(), $this->organization->id);
        $tx = $statements[0]->transactions()->first();

        $suggestions = app(MatchingService::class)->suggestFor($tx);

        $this->assertNotEmpty($suggestions);
        $this->assertTrue($suggestions[0]['target']->is($invoice));
        $this->assertContains('reference', $suggestions[0]['reasons']);
        $this->assertContains('amount', $suggestions[0]['reasons']);
    }

    public function test_confirm_sets_invoice_paid_and_writes_hash_event(): void {
        $invoice = $this->makeInvoice();
        $statements = $this->importService()->import($this->camtFile(), $this->organization->id);
        $tx = $statements[0]->transactions()->first();

        app(ReconciliationService::class)->confirm($tx, [[
            'type' => Invoice::class,
            'id' => $invoice->id,
            'amount' => 119.00,
        ]]);

        $invoice->refresh();
        $tx->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame('2026-05-15', $invoice->paid_on?->toDateString());
        $this->assertSame(MatchStatus::Matched, $tx->match_status);
        $this->assertDatabaseHas('payment_reconciliation_events', [
            'bank_transaction_id' => $tx->id,
            'event' => 'confirmed',
        ]);
    }

    public function test_partial_payment_keeps_invoice_open(): void {
        $invoice = $this->makeInvoice(total: '500.00');
        $statements = $this->importService()->import($this->camtFile(), $this->organization->id);
        $tx = $statements[0]->transactions()->first();

        app(ReconciliationService::class)->confirm($tx, [[
            'type' => Invoice::class,
            'id' => $invoice->id,
            'amount' => 119.00,
            'kind' => AllocationKind::Partial,
        ]]);

        $invoice->refresh();
        // MVP-162 (Feature 066): Teilzahlung ist jetzt ein sichtbarer
        // Zwischenstatus — vorher blieb die Rechnung stumm auf issued.
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertNull($invoice->paid_on);
    }

    public function test_unmatch_reverts_invoice_paid_reversibly(): void {
        $invoice = $this->makeInvoice();
        $statements = $this->importService()->import($this->camtFile(), $this->organization->id);
        $tx = $statements[0]->transactions()->first();

        app(ReconciliationService::class)->confirm($tx, [[
            'type' => Invoice::class,
            'id' => $invoice->id,
            'amount' => 119.00,
        ]]);
        $allocation = PaymentAllocation::query()->where('bank_transaction_id', $tx->id)->firstOrFail();

        app(ReconciliationService::class)->unmatch($allocation);

        $invoice->refresh();
        $tx->refresh();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertNull($invoice->paid_on);
        // Bankumsatz unverändert, nur Status zurück auf offen.
        $this->assertSame(MatchStatus::Unmatched, $tx->match_status);
        $this->assertSame('119.00', $tx->amount);
        $this->assertSoftDeleted('payment_allocations', ['id' => $allocation->id]);
    }

    public function test_expense_reimbursement_via_debit(): void {
        $expense = Expense::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'amount_net' => '100.00',
            'tax_rate' => '19.00',
            'amount_gross' => '119.00',
            'currency' => 'EUR',
            'reimbursed_at' => null,
        ]);

        $tx = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->organization->id])->id,
            'amount' => '119.00',
            'direction' => TransactionDirection::Debit,
            'match_status' => MatchStatus::Unmatched,
        ]);

        app(ReconciliationService::class)->confirm($tx, [[
            'type' => Expense::class,
            'id' => $expense->id,
            'amount' => 119.00,
            'kind' => AllocationKind::Reimbursement,
        ]]);

        $expense->refresh();
        $this->assertNotNull($expense->reimbursed_at);
        $this->assertNotNull($expense->reimbursement_reference);
    }

    public function test_cross_org_invoice_is_never_suggested(): void {
        // Fremde Organisation mit gleicher Rechnungsnummer.
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Fremd AG',
            'currency' => 'EUR',
        ]);
        Invoice::create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
            'number' => 'RE-2026-0007',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
        ]);

        $statements = $this->importService()->import($this->camtFile(), $this->organization->id);
        $tx = $statements[0]->transactions()->first();

        $suggestions = app(MatchingService::class)->suggestFor($tx);

        // Keine Vorschläge: die einzige passende Rechnung gehört einer fremden Org
        // (OrganizationScope blendet sie aus).
        $this->assertEmpty($suggestions);
    }

    public function test_counterparty_name_is_encrypted_at_rest(): void {
        $statements = $this->importService()->import($this->camtFile(), $this->organization->id);
        $tx = $statements[0]->transactions()->first();

        $this->assertSame('Muster GmbH', $tx->counterparty_name);

        // Roher DB-Wert darf NICHT der Klartext sein.
        $raw = DB::table('bank_transactions')->where('id', $tx->id)->value('counterparty_name');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('Muster GmbH', (string) $raw);
    }

    public function test_balance_mismatch_is_flagged(): void {
        // Manipulierte Datei: Schlusssaldo passt nicht zur Umsatzsumme.
        $xml = (string) file_get_contents(base_path('tests/Fixtures/finance/camt053_sample.xml'));
        $tampered = str_replace('<Amt Ccy="EUR">1119.00</Amt>', '<Amt Ccy="EUR">9999.00</Amt>', $xml);
        $path = sys_get_temp_dir() . '/camt_tampered_' . uniqid() . '.xml';
        file_put_contents($path, $tampered);

        $statements = $this->importService()->import(
            new UploadedFile($path, 'camt.xml', 'text/xml', null, true),
            $this->organization->id,
        );

        $this->assertSame(BalanceCheck::Mismatch, $statements[0]->balance_check);
    }

    public function test_user_without_permission_cannot_access_reconciliation(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user);

        $this->get(route('finance.reconciliation.index'))->assertForbidden();
    }
}
