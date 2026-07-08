<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTaxModelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Invoicing\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 066, MVP-162: mehrere Steuersätze je Rechnung (Positionssätze +
 * tax_breakdown, Rundung je Satz), Partei-Snapshots beim Ausstellen
 * eingefroren, Ausstellungs-Unveränderlichkeit (Model-Guard),
 * Stornorechnung (Nummernkreis S, negierte Summen, Steuerkontext),
 * Teilzahlungsstatus.
 */
final class InvoiceTaxModelTest extends TestCase {
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

    private function invoice(array $overrides = []): Invoice {
        return Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            ...$overrides,
        ]);
    }

    public function test_multiple_tax_rates_produce_breakdown(): void {
        $invoice = $this->invoice();
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Beratung',
            'quantity' => '1', 'unit' => 'h', 'unit_price' => '100.00',
            'position' => 1,
            // kein tax_rate → Kopfsatz 19 %
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Fachbuch',
            'quantity' => '2', 'unit' => 'Stk', 'unit_price' => '50.00',
            'tax_rate' => '7.00',
            'position' => 2,
        ]);

        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        $this->assertSame('200.00', number_format((float) $invoice->subtotal, 2, '.', ''));
        // 100×19 % = 19,00 + 100×7 % = 7,00 → 26,00
        $this->assertSame('26.00', number_format((float) $invoice->tax_amount, 2, '.', ''));
        $this->assertSame('226.00', number_format((float) $invoice->total, 2, '.', ''));
        $breakdown = collect($invoice->tax_breakdown)->keyBy('rate');
        $this->assertSame(7.0, $breakdown[7]['tax'] + 0.0);
        $this->assertSame(19.0, $breakdown[19]['tax'] + 0.0);
    }

    public function test_issue_freezes_parties_and_locks_invoice(): void {
        $invoice = $this->invoice();
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Leistung', 'quantity' => '1', 'unit' => 'h', 'unit_price' => '100.00', 'position' => 1,
        ]);

        $this->actingAs($this->user)
            ->post(route('invoices.issue', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        $issued = $invoice->fresh();
        $this->assertSame(Invoice::STATUS_ISSUED, $issued->status);
        $this->assertSame('ACME GmbH', $issued->party_snapshot['buyer']['name']);

        // Stammdatenänderung deutet den Snapshot NICHT um.
        $this->customer->update(['name' => 'Umfirmiert AG']);
        $this->assertSame('ACME GmbH', $invoice->fresh()->party_snapshot['buyer']['name']);

        // Unveränderlichkeit: fachliches Feld ändern → Guard wirft.
        $this->expectException(\RuntimeException::class);
        $issued->update(['notes' => 'nachträglich manipuliert']);
    }

    public function test_whitelisted_lifecycle_fields_stay_mutable_after_issue(): void {
        $invoice = $this->invoice(['status' => Invoice::STATUS_ISSUED, 'issued_on' => now()]);

        $invoice->markSent();
        $this->assertSame(1, (int) $invoice->fresh()->sent_count);

        $invoice->fresh()->update(['status' => Invoice::STATUS_PAID, 'paid_on' => now()]);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_cancellation_invoice_negates_and_keeps_tax_context(): void {
        $invoice = $this->invoice(['is_reverse_charge' => true]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Leistung', 'quantity' => '2', 'unit' => 'h', 'unit_price' => '100.00',
            'tax_rate' => '19.00', 'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();
        $invoice->update(['status' => Invoice::STATUS_ISSUED, 'issued_on' => now()]);

        $cancellation = app(InvoiceGenerator::class)->cancellationFor($invoice->fresh(), 'Falsch adressiert', $this->user->id);

        $this->assertSame(Invoice::TYPE_CANCELLATION, $cancellation->type);
        $this->assertStringStartsWith('S', $cancellation->number);
        $this->assertSame(-200.0, (float) $cancellation->subtotal);
        $this->assertTrue($cancellation->is_reverse_charge, 'Steuerkontext des Originals übernommen (§ 14c).');
        $this->assertSame(0.0, (float) $cancellation->tax_amount, 'Reverse Charge → keine Steuer.');
        $this->assertSame((int) $invoice->id, (int) $cancellation->parent_invoice_id);
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->fresh()->status);
    }

    public function test_credit_note_carries_reverse_charge(): void {
        $invoice = $this->invoice(['is_reverse_charge' => true, 'status' => Invoice::STATUS_PAID, 'issued_on' => now(), 'paid_on' => now()]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Leistung', 'quantity' => '1', 'unit' => 'h', 'unit_price' => '100.00', 'position' => 1,
        ]);

        $credit = app(InvoiceGenerator::class)->creditNoteFor($invoice->fresh(), $this->user->id);

        $this->assertTrue($credit->is_reverse_charge);
        $this->assertSame(0.0, (float) $credit->tax_amount);
    }
}
