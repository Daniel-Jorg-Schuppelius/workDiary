<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationAccountTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Billing\AccountPaymentSource;
use App\Models\Billing\{CustomerAccountPayment, CustomerBillingAgreement};
use App\Models\{Customer, User};
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Services\Finance\{BankImportException, MatchingService, ReconciliationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098: Kundenkonto als dritter allocatable-Typ der Zahlungszuordnung —
 * confirm erzeugt Konto-Zahlung (source=bank), unmatch nimmt sie zurück,
 * Rückläufer bucht Negativzeile, MatchingService schlägt Konten vor.
 */
class ReconciliationAccountTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private CustomerBillingAgreement $agreement;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Mustermann Haushalt',
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'expected_monthly_amount' => 550.00,
        ]);
    }

    private function makeTransaction(float $amount = 550.00): BankTransaction {
        return BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->organization->id])->id,
            'amount' => (string) $amount,
            'currency' => 'EUR',
        ]);
    }

    public function test_confirm_books_account_payment_and_writes_event(): void {
        $tx = $this->makeTransaction();

        app(ReconciliationService::class)->confirm($tx, [[
            'type' => CustomerBillingAgreement::class,
            'id' => $this->agreement->id,
            'amount' => 550.00,
        ]], $this->user);

        $payment = CustomerAccountPayment::query()->firstOrFail();
        $this->assertTrue($payment->source === AccountPaymentSource::Bank);
        $this->assertSame('550.00', $payment->amount?->getAmount());
        $this->assertSame($tx->id, $payment->bank_transaction_id);
        $this->assertNotNull($payment->payment_allocation_id);
        $this->assertSame($tx->booking_date->toDateString(), $payment->paid_on->toDateString());

        // Statement des Zahlmonats spiegelt die Zahlung.
        $statement = $this->agreement->statements()
            ->where('year', $payment->paid_on->year)
            ->where('month', $payment->paid_on->month)
            ->firstOrFail();
        $this->assertSame('550.00', $statement->payments_total?->getAmount());

        $this->assertDatabaseHas('payment_reconciliation_events', [
            'bank_transaction_id' => $tx->id,
            'event' => 'confirmed',
        ]);
    }

    public function test_unmatch_soft_deletes_account_payment(): void {
        $tx = $this->makeTransaction();
        app(ReconciliationService::class)->confirm($tx, [[
            'type' => CustomerBillingAgreement::class,
            'id' => $this->agreement->id,
            'amount' => 550.00,
        ]], $this->user);

        $allocation = PaymentAllocation::query()->firstOrFail();
        app(ReconciliationService::class)->unmatch($allocation, $this->user);

        $this->assertSoftDeleted('customer_account_payments', [
            'payment_allocation_id' => $allocation->id,
        ]);
        $statement = $this->agreement->statements()->orderByDesc('year')->orderByDesc('month')->first();
        $this->assertSame('0.00', $statement->payments_total?->getAmount());
    }

    public function test_process_return_books_negative_payment(): void {
        $tx = $this->makeTransaction();
        app(ReconciliationService::class)->confirm($tx, [[
            'type' => CustomerBillingAgreement::class,
            'id' => $this->agreement->id,
            'amount' => 550.00,
        ]], $this->user);
        $original = PaymentAllocation::query()->firstOrFail();
        $returnTx = $this->makeTransaction(-550.00);

        app(ReconciliationService::class)->processReturn($returnTx, $original, 'AC04', $this->user);

        $negative = CustomerAccountPayment::query()->where('amount', '<', 0)->firstOrFail();
        $this->assertSame('-550.00', $negative->amount?->getAmount());
        $this->assertStringContainsString('RET#' . $original->id, (string) $negative->note);
        // Original-Zahlung bleibt bestehen (GoBD: Zahlung ist geflossen).
        $this->assertSame(2, CustomerAccountPayment::query()->count());
    }

    public function test_matching_suggests_account_by_expected_amount_and_name(): void {
        $tx = $this->makeTransaction();
        $tx->counterparty_name = 'Mustermann Haushalt';
        $tx->save();

        $suggestions = app(MatchingService::class)->suggestFor($tx->refresh());

        $this->assertNotEmpty($suggestions);
        $this->assertTrue($suggestions[0]['target']->is($this->agreement));
        $this->assertContains('amount', $suggestions[0]['reasons']);
        $this->assertContains('customer_name', $suggestions[0]['reasons']);
    }

    public function test_cross_org_agreement_is_rejected(): void {
        $tx = $this->makeTransaction();
        $foreignOrg = \App\Models\Organization::factory()->create();
        $foreignAgreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $foreignOrg->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $foreignOrg->id])->id,
        ]);

        $this->expectException(BankImportException::class);
        app(ReconciliationService::class)->confirm($tx, [[
            'type' => CustomerBillingAgreement::class,
            'id' => $foreignAgreement->id,
            'amount' => 550.00,
        ]], $this->user);
    }
}
