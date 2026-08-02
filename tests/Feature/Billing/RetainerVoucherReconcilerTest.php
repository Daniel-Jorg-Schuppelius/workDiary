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
use App\Services\Billing\{CustomerAccountStatementService, RetainerVoucherReconciler};
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

    /** @param array<string, mixed> $attributes */
    private function voucher(string $status, float $total, float $open, string $uuid = 'lex-voucher-uuid-1', array $attributes = []): LexofficeVoucher {
        return LexofficeVoucher::query()->updateOrCreate(
            ['organization_id' => $this->organization->id, 'external_id' => $uuid],
            array_merge([
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
            ], $attributes),
        );
    }

    public function test_paid_voucher_books_lexoffice_payment(): void {
        $this->voucher('paid', 550.00, 0.00);

        $result = app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(1, $result['booked']);
        $payment = $this->agreement->payments()->firstOrFail();
        $this->assertTrue($payment->source === AccountPaymentSource::Lexoffice);
        $this->assertSame('550.00', $payment->amount?->getAmount());
        $this->assertSame('lex-voucher-uuid-1', $payment->source_reference);
        $this->assertSame(Invoice::STATUS_PAID, $this->retainerInvoice->fresh()->status);
    }

    public function test_partial_then_full_payment_grows_idempotently(): void {
        $this->voucher('open', 550.00, 200.00); // 350 bezahlt
        app(RetainerVoucherReconciler::class)->reconcile($this->organization);
        $this->assertSame('350.00', $this->agreement->payments()->firstOrFail()->amount?->getAmount());

        $this->voucher('paid', 550.00, 0.00);   // jetzt voll
        app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(1, $this->agreement->payments()->count());
        $this->assertSame('550.00', $this->agreement->payments()->firstOrFail()->amount?->getAmount());
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

    public function test_payment_is_booked_net_not_gross(): void {
        // Lexoffice führt brutto (654,50 = 550 + 19 % USt), der Leistungssaldo
        // rechnet netto — sonst wäre jede Pauschale 19 % zu hoch verbucht.
        $this->voucher('paid', 654.50, 0.00, 'lex-voucher-uuid-1', ['net_amount' => 550.00]);

        app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame('550.00', $this->agreement->payments()->firstOrFail()->amount?->getAmount());
    }

    public function test_partial_payment_is_booked_proportional_to_net(): void {
        // Halbe Bruttozahlung ⇒ halbes Netto (327,25 von 654,50 → 275,00).
        $this->voucher('open', 654.50, 327.25, 'lex-voucher-uuid-1', ['net_amount' => 550.00]);

        app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame('275.00', $this->agreement->payments()->firstOrFail()->amount?->getAmount());
    }

    public function test_existing_lexoffice_invoice_is_auto_linked_to_its_month(): void {
        // Pauschale wurde direkt in Lexoffice erstellt: keine ExternalReference,
        // aber Betrag und Monat passen → Monat bekommt den Beleg + die Zahlung.
        $statement = app(CustomerAccountStatementService::class)->ensure($this->agreement, 2026, 4);
        $voucher = $this->voucher('paid', 654.50, 0.00, 'lex-external-only', [
            'voucher_number' => 'RE-2026-0042',
            'voucher_date' => '2026-04-30',
            'net_amount' => 550.00,
        ]);

        $result = app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(1, $result['linked']);
        $this->assertSame($voucher->id, $statement->fresh()->lexoffice_voucher_id);
        $this->assertSame('550.00', $this->agreement->payments()->firstOrFail()->amount?->getAmount());
    }

    public function test_auto_link_skips_month_with_two_candidates(): void {
        // Mehrdeutig ⇒ lieber gar nicht zuordnen als falsches Geld verbuchen.
        app(CustomerAccountStatementService::class)->ensure($this->agreement, 2026, 4);
        $this->voucher('paid', 654.50, 0.00, 'lex-a', ['voucher_date' => '2026-04-10', 'net_amount' => 550.00]);
        $this->voucher('paid', 654.50, 0.00, 'lex-b', ['voucher_date' => '2026-04-20', 'net_amount' => 550.00]);

        $result = app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(0, $result['linked']);
        $this->assertSame(0, $this->agreement->payments()->count());
    }

    public function test_auto_link_skips_voucher_with_different_amount(): void {
        app(CustomerAccountStatementService::class)->ensure($this->agreement, 2026, 4);
        $this->voucher('paid', 238.00, 0.00, 'lex-other', ['voucher_date' => '2026-04-15', 'net_amount' => 200.00]);

        $result = app(RetainerVoucherReconciler::class)->reconcile($this->organization);

        $this->assertSame(0, $result['linked']);
        $this->assertSame(0, $this->agreement->payments()->count());
    }

    public function test_unlink_reverts_the_booked_payment(): void {
        $statement = app(CustomerAccountStatementService::class)->ensure($this->agreement, 2026, 4);
        $this->voucher('paid', 654.50, 0.00, 'lex-external-only', [
            'voucher_date' => '2026-04-30',
            'net_amount' => 550.00,
        ]);
        $reconciler = app(RetainerVoucherReconciler::class);
        $reconciler->reconcile($this->organization);
        $this->assertSame(1, $this->agreement->payments()->count());

        $reconciler->unlink($statement->fresh());

        $this->assertNull($statement->fresh()->lexoffice_voucher_id);
        $this->assertSame(0, $this->agreement->payments()->count());
    }

    public function test_relinking_after_unlink_revives_the_payment(): void {
        // Die stornierte Zahlung bleibt soft-deleted in der Tabelle und
        // blockiert den Unique-Index (uq_cap_source_ref) — ein erneutes
        // Verknüpfen darf daran nicht scheitern.
        $statement = app(CustomerAccountStatementService::class)->ensure($this->agreement, 2026, 4);
        $voucher = $this->voucher('paid', 654.50, 0.00, 'lex-external-only', [
            'voucher_date' => '2026-04-30',
            'net_amount' => 550.00,
        ]);
        $reconciler = app(RetainerVoucherReconciler::class);
        $reconciler->reconcile($this->organization);
        $reconciler->unlink($statement->fresh());

        $reconciler->link($statement->fresh(), $voucher);
        $reconciler->reconcile($this->organization);

        $this->assertSame(1, $this->agreement->payments()->count());
        $this->assertSame('550.00', $this->agreement->payments()->firstOrFail()->amount?->getAmount());
    }

    public function test_voided_then_paid_again_revives_the_payment(): void {
        $this->voucher('paid', 550.00, 0.00);
        $reconciler = app(RetainerVoucherReconciler::class);
        $reconciler->reconcile($this->organization);

        $this->voucher('voided', 550.00, 550.00);
        $reconciler->reconcile($this->organization);
        $this->assertSame(0, $this->agreement->payments()->count());

        $this->voucher('paid', 550.00, 0.00);
        $result = $reconciler->reconcile($this->organization);

        $this->assertSame(1, $result['booked']);
        $this->assertSame(1, $this->agreement->payments()->count());
        $this->assertSame('550.00', $this->agreement->payments()->firstOrFail()->amount?->getAmount());
    }
}
