<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteProformaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, Quote, User};
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 066, MVP-170/171/172-Rest: Angebots-Lebenszyklus (Freigabe →
 * Versand mit Token → Teilannahme mit Snapshot → Überführung ohne
 * Rückwirkung), Versionierung statt Änderung, Bindefrist, Pro-forma →
 * echte Rechnung mit neuer Nummer, Widerspruchs-Doku am Beleg.
 */
final class QuoteProformaTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function quote(): Quote {
        return app(QuoteService::class)->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
        ], [
            ['description' => 'Grundpaket', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => '19.00'],
            ['description' => 'Zusatzmodul', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => '19.00'],
            ['description' => 'Schulung (optional)', 'quantity' => 1, 'unit_price' => 300, 'tax_rate' => '19.00', 'optional' => true],
        ], $this->user);
    }

    public function test_quote_lifecycle_with_partial_acceptance_and_conversion(): void {
        $service = app(QuoteService::class);
        $quote = $this->quote();

        $this->assertStringStartsWith('AN-', $quote->number);
        // Optionen zählen im Entwurf nicht mit: 1500 netto.
        $this->assertSame(1500.0, (float) $quote->subtotal);

        $quote = $service->approve($quote, $this->user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->user);
        $this->assertNotNull($quote->acceptance_token_hash);

        // Falsches Token wird abgewiesen.
        try {
            $service->accept($quote, null, 'falsches-token');
            $this->fail('Falsches Token wurde akzeptiert.');
        } catch (\RuntimeException) {
        }

        // Teilannahme: nur Grundpaket + Option Schulung.
        $items = $quote->items()->get();
        $quote = $service->accept($quote, [(int) $items[0]->id, (int) $items[2]->id], $token);
        $this->assertSame('partially_accepted', $quote->status);
        $this->assertSame(1300.0, (float) $quote->total - (float) $quote->tax_amount);
        $this->assertNotNull($quote->decision_snapshot);

        // Überführung: nur angenommene Positionen, Angebot bleibt unverändert.
        $invoice = $service->convertToInvoice($quote, $this->user);
        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame(1300.0, (float) $invoice->subtotal);
        $this->assertSame((int) $quote->id, (int) $invoice->quote_id);
        $this->assertSame('partially_accepted', $quote->fresh()->status, 'Keine Rückwirkung.');

        // Nachträgliche Angebotsänderung wirkt NICHT auf den Snapshot.
        $quote->items()->first()->update(['unit_price' => '9999.00']);
        $this->assertSame(1000.0, (float) $quote->fresh()->decision_snapshot['items'][0]['unit_price']);
    }

    /**
     * Vollaudit 2026-07 (H4): Positionsrabatte überleben Snapshot und
     * Überführung — die konvertierte Rechnung darf nie mehr fordern als
     * den angenommenen Angebots-Total.
     */
    public function test_item_discounts_survive_acceptance_snapshot_and_conversion(): void {
        $service = app(QuoteService::class);
        $quote = $service->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
        ], [
            ['description' => 'Grundpaket', 'quantity' => 1, 'unit' => 'Pausch.', 'unit_price' => 1000, 'tax_rate' => '19.00', 'discount_percent' => '10.00'],
            ['description' => 'Zusatzmodul', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => '19.00', 'discount_amount' => '50.00'],
        ], $this->user);

        // 900 + 450 = 1350 netto rabattiert.
        $this->assertSame(1350.0, (float) $quote->subtotal);

        $quote = $service->approve($quote, $this->user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->user);
        $quote = $service->accept($quote, null, $token);

        $snapshot = $quote->decision_snapshot;
        $this->assertSame(10.0, (float) $snapshot['items'][0]['discount_percent']);
        $this->assertSame(50.0, (float) $snapshot['items'][1]['discount_amount']);
        $this->assertSame('Pausch.', $snapshot['items'][0]['unit']);

        $invoice = $service->convertToInvoice($quote, $this->user);
        $items = $invoice->items()->orderBy('position')->get();
        $this->assertSame(10.0, (float) $items[0]->discount_percent);
        $this->assertSame(50.0, (float) $items[1]->discount_amount);
        $this->assertSame('Pausch.', $items[0]->unit);
        $this->assertSame(1350.0, (float) $invoice->subtotal);
        $this->assertSame((float) $snapshot['total'], (float) $invoice->total, 'Rechnungs-Total == angenommener Angebots-Total.');
    }

    /** Vollaudit 2026-07 (H4): Alt-Snapshots ohne unit/discount-Felder konvertieren weiter (null-Fallback). */
    public function test_legacy_snapshot_without_discount_fields_still_converts(): void {
        $service = app(QuoteService::class);
        $quote = $service->approve($this->quote(), $this->user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->user);
        $quote = $service->accept($quote, null, $token);

        $snapshot = $quote->decision_snapshot;
        $snapshot['items'] = array_map(
            fn(array $item): array => array_diff_key($item, array_flip(['unit', 'discount_percent', 'discount_amount'])),
            $snapshot['items'],
        );
        $quote->forceFill(['decision_snapshot' => $snapshot])->save();

        $invoice = $service->convertToInvoice($quote->fresh(), $this->user);

        $this->assertSame(2, $invoice->items()->count());
        $this->assertNull($invoice->items()->first()->discount_percent);
        $this->assertSame(1500.0, (float) $invoice->subtotal);
    }

    /**
     * Whitebox 2026-07-10 (G3/G4): Kleinunternehmer-Org (§ 19 UStG) —
     * das Angebot fällt ohne Positionssatz NICHT auf 19 % zurück, und die
     * Überführung nutzt den TaxResolver statt hartkodierter 19 % (sonst
     * unrichtiger Steuerausweis nach § 14c UStG).
     */
    public function test_small_business_quote_and_conversion_show_no_tax(): void {
        $this->org->update(['settings' => ['einvoice' => ['small_business' => '1']]]);
        $service = app(QuoteService::class);

        $quote = $service->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
        ], [
            ['description' => 'Grundpaket', 'quantity' => 1, 'unit_price' => 1000], // bewusst OHNE tax_rate
        ], $this->user);

        $this->assertSame(1000.0, (float) $quote->subtotal);
        $this->assertSame(0.0, (float) $quote->tax_amount, 'Kein 19-%-Fallback für die §-19-Org.');
        $this->assertSame(1000.0, (float) $quote->total);

        $quote = $service->approve($quote, $this->user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->user);
        $quote = $service->accept($quote, null, $token);

        $invoice = $service->convertToInvoice($quote, $this->user);

        $this->assertSame('0.00', (string) $invoice->tax_rate);
        $this->assertSame(0.0, (float) $invoice->tax_amount);
        $this->assertSame(1000.0, (float) $invoice->total);
        $this->assertStringContainsString('§ 19', (string) $invoice->notes);
    }

    public function test_sent_quotes_are_versioned_not_edited(): void {
        $service = app(QuoteService::class);
        $quote = $service->approve($this->quote(), $this->user);
        ['quote' => $quote] = $service->send($quote, $this->user);

        $next = $service->newVersion($quote, $this->user);

        $this->assertSame(2, $next->version);
        $this->assertSame($quote->number, $next->number);
        $this->assertSame('draft', $next->status);
        $this->assertSame(3, $next->items()->count());
        $this->assertSame((int) $quote->id, (int) $next->previous_version_id);
    }

    public function test_expired_quote_cannot_be_accepted(): void {
        $service = app(QuoteService::class);
        $quote = $service->approve($this->quote(), $this->user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->user);
        $quote->update(['valid_until' => now()->subDay()]);

        try {
            $service->accept($quote->fresh(), null, $token);
            $this->fail('Abgelaufenes Angebot wurde angenommen.');
        } catch (\RuntimeException) {
        }
        $this->assertSame('expired', $quote->fresh()->status);
    }

    public function test_proforma_converts_to_real_invoice_with_new_number(): void {
        $proforma = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'PF-2026-0001',
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_PROFORMA,
            'tax_rate' => '19.00',
        ]);
        $proforma->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Warenmuster', 'quantity' => '1', 'unit' => 'Stk', 'unit_price' => '250.00',
            'discount_percent' => '10.00', 'position' => 1,
        ]);

        $invoice = app(QuoteService::class)->proformaToInvoice($proforma->fresh(), $this->user);

        // Vollaudit 2026-07 (H4): Positionsrabatt überlebt auch die Pro-forma-Umwandlung.
        $this->assertSame(10.0, (float) $invoice->items()->first()->discount_percent);
        $this->assertSame(225.0, (float) $invoice->subtotal);
        $this->assertSame(Invoice::TYPE_INVOICE, $invoice->type);
        $this->assertStringStartsWith('R', $invoice->number);
        $this->assertNotSame($proforma->number, $invoice->number);
        $this->assertSame((int) $proforma->id, (int) $invoice->parent_invoice_id);
        $this->assertSame(Invoice::TYPE_PROFORMA, $proforma->fresh()->type, 'Pro-forma bleibt unangetastet.');

        // Normale Rechnung kann NICHT als Pro-forma umgewandelt werden.
        $this->expectException(\RuntimeException::class);
        app(QuoteService::class)->proformaToInvoice($invoice, $this->user);
    }

    public function test_objection_is_documented_on_credit_note(): void {
        $credit = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'G2026-0001',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_CREDIT_NOTE,
            'tax_rate' => '19.00',
            'issued_on' => now(),
        ]);

        // Vollaudit 2026-07 (M27): über die UI-Aktion statt Direkt-Update —
        // Pflichtnote, Audit-Event, kein Doppel-Widerspruch.
        $this->actingAs($this->user)
            ->post(route('invoices.objection', $credit), [
                'objection_note' => 'Empfänger widerspricht der Gutschrift (§ 14 Abs. 2 UStG).',
            ])
            ->assertRedirect();

        $credit->refresh();
        $this->assertNotNull($credit->objection_at);
        $this->assertStringContainsString('widerspricht', (string) $credit->objection_note);
        $this->assertDatabaseHas('audit_logs', ['event' => 'invoice.objectionDocumented']);

        // Doppelter Widerspruch wird abgewiesen.
        $this->actingAs($this->user)
            ->post(route('invoices.objection', $credit), ['objection_note' => 'Noch ein Widerspruch, unzulässig.'])
            ->assertSessionHas('error');
    }
}
