<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinalInvoiceRetentionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\{RetentionBase, RetentionKind};
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceRetention};
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\{InvoiceGenerator, RetentionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/** MVP-979: Sicherheitseinbehalt erst in der Schlussrechnung, bemessen an der Gesamtleistung. */
final class FinalInvoiceRetentionTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function draft(string $net = '1000.00'): Invoice {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id, 'customer_id' => $this->customer->id, 'number' => 'R-' . uniqid(),
            'status' => Invoice::STATUS_DRAFT, 'type' => Invoice::TYPE_INVOICE, 'currency' => 'EUR', 'tax_rate' => '19.00',
        ]);
        $invoice->items()->create(['organization_id' => $this->org->id, 'description' => 'Gesamtleistung', 'quantity' => '1', 'unit' => 'pausch.', 'unit_price' => $net, 'position' => 1]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }

    private function issuedDownPayment(string $net): Invoice {
        $dp = app(InvoiceGenerator::class)->downPaymentFor($this->customer, null, 'Abschlag', $net);
        $dp->update(['status' => Invoice::STATUS_ISSUED, 'issued_on' => now()]);

        return $dp->fresh();
    }

    public function test_down_payments_take_no_retention(): void {
        $dp = app(InvoiceGenerator::class)->downPaymentFor($this->customer, null, 'Abschlag', '400.00');
        $this->assertFalse($dp->acceptsRetention());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('invoicing.retention.final_only'));
        app(RetentionService::class)->add($dp, RetentionKind::Warranty, 5.0, null, null, $this->user);
    }

    public function test_final_invoice_measures_the_percentage_on_the_total_performance(): void {
        $this->issuedDownPayment('400.00');
        $final = app(InvoiceGenerator::class)->finalFromDraft($this->draft());
        $this->assertSame('600.00', $final->subtotal?->getAmount());

        $net = app(RetentionService::class)->add($final, RetentionKind::Warranty, 5.0, null, null, $this->user);
        $this->assertSame('1000.00', $net->base_amount->getAmount());
        $this->assertSame('50.00', $net->amount->getAmount());

        $gross = app(RetentionService::class)->add($final, RetentionKind::Performance, 5.0, null, null, $this->user, null, RetentionBase::Gross);
        $this->assertSame('1190.00', $gross->base_amount->getAmount());
        $this->assertSame('59.50', $gross->amount->getAmount());
    }

    public function test_conversion_rejects_retentions_above_the_remaining_amount(): void {
        $this->issuedDownPayment('500.00');
        $draft = $this->draft();
        app(RetentionService::class)->add($draft, RetentionKind::Performance, null, 800.0, null, $this->user);

        try {
            app(InvoiceGenerator::class)->finalFromDraft($draft);
            $this->fail('Ein Einbehalt über dem Zahlbetrag der Schlussrechnung wurde angenommen.');
        } catch (ValidationException $e) {
            $this->assertSame(__('invoicing.retention.exceeds_after_settlement'), $e->errors()['invoice'][0] ?? null);
        }
        $this->assertSame(Invoice::TYPE_INVOICE, $draft->fresh()->type);
        $this->assertSame(1, InvoiceRetention::query()->count());
    }
}
