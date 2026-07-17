<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceDiscountSkontoTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\AllocationKind;
use App\Models\{Customer, Invoice, User};
use App\Services\Finance\MatchingService;
use App\Services\Invoicing\InvoiceGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-416: Positions-/Belegrabatt und Skonto — Summenlogik (Zuordnung je
 * Steuersatz, Rundung), Storno-Spiegelung, Konditionen-Endpoint,
 * XRechnung-Allowances + #SKONTO#-Zahlungsbedingung und beleggenauer
 * Zahlungsabgleich.
 */
class InvoiceDiscountSkontoTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'WorkDiary Org']);
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'WorkDiary GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@workdiary.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'WorkDiary GmbH',
            'payment_terms_days' => 14,
        ]]]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function makeInvoice(array $items, array $attributes = [], string $status = Invoice::STATUS_DRAFT): Invoice {
        // Snapshot erst NACH recalculate()/save() setzen — der Ausstellungs-Guard
        // blockiert sonst (korrekt) die Summenfelder.
        $partySnapshot = $attributes['party_snapshot'] ?? null;
        unset($attributes['party_snapshot']);

        $invoice = Invoice::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'issued_on' => '2026-06-01',
            'due_on' => '2026-06-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ], $attributes));

        $position = 0;
        foreach ($items as $item) {
            $invoice->items()->create(array_merge([
                'organization_id' => $this->organization->id,
                'description' => 'Leistung',
                'unit' => 'Std.',
                'position' => ++$position,
            ], $item));
        }

        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        if ($partySnapshot !== null) {
            $invoice->forceFill(['party_snapshot' => $partySnapshot])->saveQuietly();
        }

        return $invoice->fresh(['items', 'customer']);
    }

    // ── Summenlogik ─────────────────────────────────────────────────────────

    public function test_line_discount_percent_and_amount_reduce_line_net(): void {
        $invoice = $this->makeInvoice([
            ['quantity' => '2.00', 'unit_price' => '100.00', 'discount_percent' => '10.00'],
            ['quantity' => '1.00', 'unit_price' => '100.00', 'discount_amount' => '15.00'],
        ]);

        $this->assertSame('180.00', (string) $invoice->items[0]->amount);
        $this->assertSame('85.00', (string) $invoice->items[1]->amount);
        $this->assertSame('265.00', (string) $invoice->subtotal);
    }

    public function test_document_discount_allocates_per_tax_rate_with_exact_sum(): void {
        $invoice = $this->makeInvoice([
            ['quantity' => '1.00', 'unit_price' => '100.00', 'tax_rate' => '19.00'],
            ['quantity' => '1.00', 'unit_price' => '50.00', 'tax_rate' => '7.00'],
        ], ['discount_amount' => '10.00']);

        // 10 € anteilig: 6,67 (19 %) + 3,33 (7 %); Steuer auf die rabattierte Basis.
        $this->assertSame('140.00', (string) $invoice->subtotal);
        $this->assertSame(140.00, round($invoice->lineSubtotal() - 10.00, 2));
        $breakdown = collect($invoice->tax_breakdown);
        $this->assertEqualsWithDelta(46.67, (float) $breakdown->firstWhere('rate', 7.0)['net'], 0.001);
        $this->assertEqualsWithDelta(93.33, (float) $breakdown->firstWhere('rate', 19.0)['net'], 0.001);
        $this->assertSame('21.00', (string) $invoice->tax_amount);
        $this->assertSame('161.00', (string) $invoice->total);
    }

    public function test_cancellation_mirrors_discounted_totals_exactly(): void {
        $invoice = $this->makeInvoice([
            ['quantity' => '2.00', 'unit_price' => '100.00', 'discount_percent' => '10.00'],
            ['quantity' => '1.00', 'unit_price' => '100.00', 'discount_amount' => '15.00'],
        ], ['discount_amount' => '20.00'], Invoice::STATUS_ISSUED);

        $cancellation = app(InvoiceGenerator::class)->cancellationFor($invoice, 'Test', (int) $this->admin->id);

        $this->assertEqualsWithDelta(-1 * (float) $invoice->total, (float) $cancellation->total, 0.001);
        $this->assertEqualsWithDelta(-1 * (float) $invoice->subtotal, (float) $cancellation->subtotal, 0.001);
        $this->assertEqualsWithDelta(-1 * (float) $invoice->tax_amount, (float) $cancellation->tax_amount, 0.001);
    }

    // ── Konditionen-Endpoint ───────────────────────────────────────────────

    public function test_conditions_can_be_set_on_draft_and_recalculate(): void {
        $invoice = $this->makeInvoice([['quantity' => '1.00', 'unit_price' => '100.00']]);

        $this->actingAs($this->admin)
            ->patch(route('invoices.conditions.update', $invoice), [
                'discount_percent' => '10',
                'skonto_percent' => '2',
                'skonto_days' => '14',
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('90.00', (string) $invoice->subtotal);
        $this->assertTrue($invoice->hasSkonto());
        $this->assertSame('2026-06-15', $invoice->skontoDeadline()?->toDateString());
    }

    public function test_conditions_are_locked_after_issue_and_validate_xor(): void {
        $issued = $this->makeInvoice(
            [['quantity' => '1.00', 'unit_price' => '100.00']],
            ['party_snapshot' => ['frozen' => true]],
            Invoice::STATUS_ISSUED,
        );

        $this->actingAs($this->admin)
            ->patch(route('invoices.conditions.update', $issued), ['discount_percent' => '5'])
            ->assertForbidden();

        $draft = $this->makeInvoice([['quantity' => '1.00', 'unit_price' => '100.00']]);
        $this->actingAs($this->admin)
            ->patch(route('invoices.conditions.update', $draft), [
                'discount_percent' => '5',
                'discount_amount' => '5',
            ])
            ->assertSessionHasErrors('discount_percent');
    }

    // ── XRechnung ──────────────────────────────────────────────────────────

    public function test_xrechnung_contains_allowances_and_structured_skonto(): void {
        $invoice = $this->makeInvoice([
            ['quantity' => '2.00', 'unit_price' => '100.00', 'discount_percent' => '10.00'],
        ], [
            'discount_amount' => '18.00',
            'skonto_percent' => '2.00',
            'skonto_days' => 14,
        ], Invoice::STATUS_ISSUED);

        $response = $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice));

        $response->assertOk();
        $xml = (string) $response->getContent();
        $this->assertStringContainsString('<cac:AllowanceCharge>', $xml);
        $this->assertStringContainsString('#SKONTO#TAGE=14#PROZENT=2.00#', $xml);
        // Netto nach allen Rabatten: 180 − 18 = 162,00 (BT-109).
        $this->assertStringContainsString('162.00', $xml);
    }

    // ── Zahlungsabgleich ───────────────────────────────────────────────────

    public function test_matching_uses_invoice_skonto_condition(): void {
        $invoice = $this->makeInvoice(
            [['quantity' => '1.00', 'unit_price' => '100.00']],
            ['skonto_percent' => '2.00', 'skonto_days' => 14],
            Invoice::STATUS_ISSUED,
        );
        // total = 119,00; Skonto 2 % = 2,38 → 116,62 innerhalb der Frist.
        $matching = app(MatchingService::class);

        $withinDeadline = $matching->minAcceptableFor($invoice, Carbon::parse('2026-06-10'));
        $afterDeadline = $matching->minAcceptableFor($invoice, Carbon::parse('2026-07-01'));

        $this->assertSame(116.62, $withinDeadline);
        $this->assertSame(119.00, $afterDeadline);
        $this->assertSame(AllocationKind::Payment, $matching->kindForInvoice(116.62, 119.00, false, $withinDeadline));
        $this->assertSame(AllocationKind::Partial, $matching->kindForInvoice(116.62, 119.00, false, $afterDeadline));
    }
}
