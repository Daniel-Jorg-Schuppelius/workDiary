<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerVoucherReconcilerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\Billing\AccountPaymentSource;
use App\Enums\Finance\BillingMode;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, ExternalReference, Invoice, LexofficeVoucher};
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin};
use App\Services\Billing\RetainerVoucherReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098: Lexoffice-Zahlstatus der Retainer-Belege fließt idempotent in
 * den Leistungssaldo (source=lexoffice). Teilzahlung wächst, Storno nimmt
 * zurück, fremde Belege werden ignoriert.
 */
class RetainerVoucherReconcilerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private CustomerBillingAgreement $agreement;

    private Invoice $retainerInvoice;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'billing_mode' => BillingMode::Lexoffice->value,
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'mode' => 'retainer',
            'expected_monthly_amount' => 550.00,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'hourly_rate' => 16.50,
        ]);

        // Retainer-Beleg + Lexoffice-ExternalReference (wie nach publish()).
        $this->retainerInvoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'RE-2026-0001',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_RETAINER,
            'currency' => 'EUR',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficeInvoiceService::EXT_TYPE_INVOICE,
            'referenceable_type' => $this->retainerInvoice->getMorphClass(),
            'referenceable_id' => $this->retainerInvoice->id,
            'external_id' => 'lex-voucher-uuid-1',
            'synced_at' => now(),
        ]);
    }

    private function voucher(string $status, float $total, float $open, string $uuid = 'lex-voucher-uuid-1'): LexofficeVoucher {
        return LexofficeVoucher::query()->updateOrCreate(
            ['organization_id' => $this->organization->id, 'external_id' => $uuid],
            [
                'customer_id' => $this->customer->id,
                'contact_external_id' => 'contact-1',
                'voucher_type' => 'salesinvoice',
                'voucher_status' => $status,
                'voucher_number' => 'RE-2026-0001',
                'voucher_date' => '2026-03-31',
                'total_amount' => $total,
                'open_amount' => $open,
                'currency' => 'EUR',
                'archived' => false,
            ],
        );
    }

    public function test_paid_voucher_books_lexoffice_payment(): void {
        $this->voucher('paid', 550.00, 0.00);

        $result = app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(1, $result['booked']);
        $payment = $this->agreement->payments()->firstOrFail();
        $this->assertTrue($payment->source === AccountPaymentSource::Lexoffice);
        $this->assertSame('550.00', $payment->amount);
        $this->assertSame('lex-voucher-uuid-1', $payment->source_reference);
        $this->assertSame(Invoice::STATUS_PAID, $this->retainerInvoice->fresh()->status);
    }

    public function test_partial_then_full_payment_grows_idempotently(): void {
        $this->voucher('open', 550.00, 200.00); // 350 bezahlt
        app(RetainerVoucherReconciler::class)->reconcile($this->organization);
        $this->assertSame('350.00', $this->agreement->payments()->firstOrFail()->amount);

        $this->voucher('paid', 550.00, 0.00);   // jetzt voll
        app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(1, $this->agreement->payments()->count());
        $this->assertSame('550.00', $this->agreement->payments()->firstOrFail()->amount);
    }

    public function test_voided_voucher_revokes_payment(): void {
        $this->voucher('paid', 550.00, 0.00);
        app(RetainerVoucherReconciler::class)->reconcile($this->organization);
        $this->assertSame(1, $this->agreement->payments()->count());

        $this->voucher('voided', 550.00, 550.00);
        $result = app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(1, $result['revoked']);
        $this->assertSame(0, $this->agreement->payments()->count());
        $this->assertSame(Invoice::STATUS_CANCELLED, $this->retainerInvoice->fresh()->status);
    }

    public function test_voucher_without_retainer_invoice_is_ignored(): void {
        // Beleg eines fremden (Nicht-Retainer-)Belegs → keine Buchung.
        $this->voucher('paid', 99.00, 0.00, 'unrelated-uuid');

        $result = app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(0, $result['booked']);
        $this->assertSame(0, $this->agreement->payments()->count());
    }
}
