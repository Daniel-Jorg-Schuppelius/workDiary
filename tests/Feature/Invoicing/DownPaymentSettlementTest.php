<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DownPaymentSettlementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Invoicing\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feature 066 (Belegkette, Restpaket): Abschlags-/Anzahlungsrechnung als
 * eigener Belegtyp, Teilrechnungs-Kennzeichnung und Schlussrechnung mit
 * Absetzung der offenen Abschläge je Steuersatz (§ 14 Abs. 5 UStG). Die
 * Abschlagsrechnung bleibt unverändert; die Verknüpfung lebt auf den
 * Absetzungspositionen (settled_invoice_id) — Storno der Schlussrechnung
 * öffnet die Abschläge automatisch wieder.
 */
final class DownPaymentSettlementTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->org->id,
            'name' => 'ACME GmbH',
            'address_street' => 'Werkstr. 1',
            'address_zip' => '44135',
            'address_city' => 'Dortmund',
        ]);
    }

    private function generator(): InvoiceGenerator {
        return app(InvoiceGenerator::class);
    }

    private function draftInvoice(array $overrides = []): Invoice {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_INVOICE,
            // Produktivpfade setzen currency immer (Generator); ohne explizite
            // Angabe greift der DB-Default erst nach einem Reload.
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            ...$overrides,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Gesamtleistung',
            'quantity' => '1', 'unit' => 'pausch.', 'unit_price' => '1000.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }

    /** Abschlagsrechnung über den Generator erzeugen und direkt ausstellen. */
    private function issuedDownPayment(string $net): Invoice {
        $dp = $this->generator()->downPaymentFor($this->customer, null, 'Abschlag Projektstart', $net);
        // Ohne Partei-Snapshot bleibt der Fixture-Beleg schreibbar (Guard-Anker).
        $dp->update(['status' => Invoice::STATUS_ISSUED, 'issued_on' => now()]);

        return $dp->fresh();
    }

    public function test_down_payment_generator_creates_flat_draft(): void {
        $dp = $this->generator()->downPaymentFor($this->customer, null, 'Abschlag Projektstart', '300.00');

        $this->assertSame(Invoice::TYPE_DOWN_PAYMENT, $dp->type);
        $this->assertSame(Invoice::STATUS_DRAFT, $dp->status);
        $this->assertStringStartsWith('R', $dp->number);
        $this->assertCount(1, $dp->items);
        $this->assertSame('300.00', number_format((float) $dp->subtotal, 2, '.', ''));
        $this->assertSame('57.00', number_format((float) $dp->tax_amount, 2, '.', ''));
        $this->assertStringContainsString('§ 14 Abs. 5 UStG', (string) $dp->notes);
    }

    public function test_final_from_draft_offsets_issued_down_payments_per_rate(): void {
        $dp1 = $this->issuedDownPayment('300.00');
        $dp2 = $this->issuedDownPayment('200.00');
        $draft = $this->draftInvoice();

        $final = $this->generator()->finalFromDraft($draft);

        $this->assertSame(Invoice::TYPE_FINAL, $final->type);
        $this->assertCount(3, $final->items);

        $deductions = $final->items->whereNotNull('settled_invoice_id')->values();
        $this->assertSame([$dp1->id, $dp2->id], $deductions->pluck('settled_invoice_id')->all());
        $this->assertSame(['-300.00', '-200.00'], $deductions->map(fn($i) => number_format((float) $i->amount, 2, '.', ''))->all());

        // 1000 − 300 − 200 = 500 netto; 19 % darauf = 95 → 595 brutto.
        $this->assertSame('500.00', number_format((float) $final->subtotal, 2, '.', ''));
        $this->assertSame('95.00', number_format((float) $final->tax_amount, 2, '.', ''));
        $this->assertSame('595.00', number_format((float) $final->total, 2, '.', ''));
        $this->assertStringContainsString($dp1->number, (string) $final->notes);
        $this->assertStringContainsString($dp2->number, (string) $final->notes);
    }

    public function test_draft_down_payments_are_not_offset(): void {
        // Nur ein ENTWURF eines Abschlags — nicht vereinnahmbar, nicht anrechenbar.
        $this->generator()->downPaymentFor($this->customer, null, 'Abschlag', '300.00');
        $draft = $this->draftInvoice();

        $this->expectException(ValidationException::class);
        $this->generator()->finalFromDraft($draft);
    }

    public function test_settled_down_payment_reopens_when_final_is_cancelled(): void {
        $dp = $this->issuedDownPayment('300.00');
        $final = $this->generator()->finalFromDraft($this->draftInvoice());

        $this->assertCount(0, $this->generator()->openDownPaymentsFor($this->customer, null, 'EUR'));

        // Storno der Schlussrechnung öffnet den Abschlag wieder (Abfrage-
        // Semantik statt Mutation der Abschlagsrechnung).
        $final->update(['status' => Invoice::STATUS_CANCELLED]);
        $this->assertSame([$dp->id], $this->generator()->openDownPaymentsFor($this->customer, null, 'EUR')->pluck('id')->all());
    }

    public function test_final_must_not_become_negative(): void {
        $this->issuedDownPayment('2000.00');
        $draft = $this->draftInvoice();

        try {
            $this->generator()->finalFromDraft($draft);
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
            // Transaktion rollt zurück: Entwurf bleibt unverändert.
            $fresh = $draft->fresh();
            $this->assertSame(Invoice::TYPE_INVOICE, $fresh->type);
            $this->assertCount(1, $fresh->items);
        }
    }

    public function test_mixed_rate_down_payment_deducts_per_rate(): void {
        $dp = $this->generator()->downPaymentFor($this->customer, null, 'Abschlag', '100.00');
        $dp->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Abschlag ermäßigt',
            'quantity' => '1', 'unit' => 'pausch.', 'unit_price' => '50.00',
            'tax_rate' => '7.00',
            'position' => 2,
        ]);
        $dp->load('items');
        $dp->recalculate();
        $dp->save();
        $dp->update(['status' => Invoice::STATUS_ISSUED, 'issued_on' => now()]);

        $final = $this->generator()->finalFromDraft($this->draftInvoice());

        $deductions = $final->items->whereNotNull('settled_invoice_id')->values();
        $this->assertCount(2, $deductions);
        // tax_breakdown ist je Satz aufsteigend sortiert (ksort in recalculate).
        $this->assertSame(['7.00', '19.00'], $deductions->map(fn($i) => number_format((float) $i->tax_rate, 2, '.', ''))->all());
        // Netto: 1000 − 100 − 50 = 850; Steuer: 900×19 % + (−50)×7 % = 171,00 − 3,50.
        $this->assertSame('850.00', number_format((float) $final->subtotal, 2, '.', ''));
        $this->assertSame('167.50', number_format((float) $final->tax_amount, 2, '.', ''));
    }

    public function test_store_endpoint_creates_down_payment_and_partial_mark(): void {
        $response = $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->sqid,
            'content' => 'down_payment',
            'dp_description' => 'Abschlag Projektstart',
            'dp_amount' => '250.00',
        ]);

        $dp = Invoice::query()->where('type', Invoice::TYPE_DOWN_PAYMENT)->firstOrFail();
        $response->assertRedirect(route('invoices.show', $dp));
        $this->assertSame('250.00', number_format((float) $dp->subtotal, 2, '.', ''));
    }

    public function test_make_final_endpoint_converts_draft(): void {
        $this->issuedDownPayment('300.00');
        $draft = $this->draftInvoice();

        $this->actingAs($this->user)
            ->post(route('invoices.final', $draft))
            ->assertRedirect(route('invoices.show', $draft));

        $this->assertSame(Invoice::TYPE_FINAL, $draft->fresh()->type);
    }

    public function test_make_final_endpoint_rejects_special_types(): void {
        $proforma = $this->generator()->emptyProforma($this->customer);

        $this->actingAs($this->user)
            ->post(route('invoices.final', $proforma))
            ->assertSessionHas('error');

        $this->assertSame(Invoice::TYPE_PROFORMA, $proforma->fresh()->type);
    }
}
