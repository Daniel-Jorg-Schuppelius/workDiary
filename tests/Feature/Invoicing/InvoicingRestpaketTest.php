<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicingRestpaketTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Mail\DunningMail;
use App\Models\{Customer, Invoice, InvoiceDispatch, Organization, Quote, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Feature 066, Restpaket (MVP-163/168/170/171): Mahn-Mailversand mit
 * Zustellnachweis, Pro-forma-UI-Fluss (eigener Nummernkreis, keine
 * Ausstellung, kontrollierte Umwandlung), Zahlungsziel-Formularfeld,
 * §-19-Fußtext auf dem PDF sowie Angebots-UI inkl. Portal-Annahme.
 */
final class InvoicingRestpaketTest extends TestCase {
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
            'country' => 'DE',
            'email' => 'buchhaltung@kunde.example',
        ]);
    }

    private function overdueInvoice(): Invoice {
        return Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-7001',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            'total' => '119.00',
            'issued_on' => now()->subDays(30),
            'due_on' => now()->subDays(10),
        ]);
    }

    public function test_dun_sends_reminder_mail_and_records_dispatch(): void {
        Mail::fake();
        $invoice = $this->overdueInvoice();

        $this->actingAs($this->user)
            ->post(route('invoices.dun', $invoice), [
                'send_mail' => '1',
                'email' => 'debitor@kunde.example',
                'note' => 'Bitte kurzfristig ausgleichen.',
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(1, (int) $invoice->dunning_level);
        $this->assertNotNull($invoice->dunned_at);

        Mail::assertQueued(DunningMail::class, function (DunningMail $mail): bool {
            return $mail->level === 1 && $mail->hasTo('debitor@kunde.example');
        });

        $dispatch = InvoiceDispatch::query()->firstOrFail();
        $this->assertSame(InvoiceDispatch::CHANNEL_EMAIL, $dispatch->channel);
        $this->assertSame('debitor@kunde.example', $dispatch->recipient);
        $this->assertSame('dunning', (string) data_get($dispatch->meta, 'kind'));

        // Mahn-Dialog ist erreichbar, solange Stufe < 3.
        $this->actingAs($this->user)->get(route('invoices.dun.form', $invoice))->assertOk();
    }

    public function test_dun_requires_overdue_invoice(): void {
        $invoice = $this->overdueInvoice();
        $invoice->update(['due_on' => now()->addDays(5)]);

        $this->actingAs($this->user)
            ->post(route('invoices.dun', $invoice))
            ->assertSessionHas('error');
        $this->assertSame(0, (int) $invoice->fresh()->dunning_level);
    }

    public function test_proforma_flow_creates_pf_number_blocks_issue_and_converts(): void {
        // Anlage über den Dialog: eigener PF-Nummernkreis, Zahlungsziel aus dem Formular.
        $this->actingAs($this->user)->post(route('invoices.store'), [
            'customer_id' => $this->customer->sqid,
            'content' => 'proforma',
            'payment_terms_days' => 30,
        ])->assertRedirect();

        $proforma = Invoice::query()->where('type', Invoice::TYPE_PROFORMA)->firstOrFail();
        $this->assertStringStartsWith('PF', $proforma->number);
        $this->assertSame(30, (int) $proforma->payment_terms_days);
        $this->assertStringContainsString('Pro-forma', (string) $proforma->notes);

        $proforma->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Warenmuster',
            'quantity' => '1', 'unit' => 'Stk', 'unit_price' => '250.00', 'position' => 1,
        ]);

        // Keine Ausstellung, keine E-Rechnung für Pro-forma.
        $this->actingAs($this->user)->post(route('invoices.issue', $proforma))->assertSessionHas('error');
        $this->assertSame(Invoice::STATUS_DRAFT, $proforma->fresh()->status);
        $this->actingAs($this->user)->get(route('invoices.einvoice', $proforma))->assertNotFound();
        $this->actingAs($this->user)->get(route('invoices.zugferd', $proforma))->assertNotFound();

        // Kontrollierte Umwandlung: neue R-Nummer, Pro-forma bleibt unangetastet.
        $this->actingAs($this->user)->post(route('invoices.proforma-convert', $proforma))->assertRedirect();
        $real = Invoice::query()->where('type', Invoice::TYPE_INVOICE)->firstOrFail();
        $this->assertSame((int) $proforma->id, (int) $real->parent_invoice_id);
        $this->assertSame(Invoice::TYPE_PROFORMA, $proforma->fresh()->type);
    }

    public function test_pdf_shows_small_business_footer(): void {
        $this->org->update(['settings' => ['einvoice' => ['small_business' => '1']]]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-7002',
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '0.00',
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Beratung', 'quantity' => '1', 'unit' => 'Std.', 'unit_price' => '100.00', 'position' => 1,
        ]);
        $invoice->load('items', 'customer', 'organization');

        $html = view('invoices.pdf', app(\App\Services\Invoicing\InvoicePdfRenderer::class)->viewData($invoice->fresh(['items', 'customer'])))->render();

        $this->assertStringContainsString('§ 19 UStG', $html);
    }

    public function test_quote_ui_lifecycle_with_portal_acceptance_and_conversion(): void {
        // Anlegen über den Dialog (Sqid-Eingabe).
        $this->actingAs($this->user)->post(route('quotes.store'), [
            'customer_id' => $this->customer->sqid,
            'valid_until' => now()->addDays(30)->toDateString(),
            'terms' => 'Zahlbar innerhalb von 14 Tagen.',
        ])->assertRedirect();

        $quote = Quote::query()->firstOrFail();
        $this->assertStringStartsWith('AN', $quote->number);

        // Position über den Dialog, dann Freigabe + Versand.
        $this->actingAs($this->user)->post(route('quotes.items.store', $quote), [
            'description' => 'Wartung', 'quantity' => '2', 'unit' => 'Std.', 'unit_price' => '90.00',
        ])->assertRedirect();
        $this->assertEqualsWithDelta(180.0, $quote->fresh()->subtotal?->toFloat(), 0.01);

        $this->actingAs($this->user)->post(route('quotes.approve', $quote))->assertRedirect();
        $response = $this->actingAs($this->user)->post(route('quotes.send', $quote));
        $response->assertSessionHas('acceptance_url');
        $url = (string) session('acceptance_url');
        $this->assertStringContainsString('/annahme', $url);

        // Portal (ohne Login): falsches Token 404, richtiges Token 200 + Annahme.
        auth()->logout();
        $this->get(route('quotes.portal.show', ['quote' => $quote->getRouteKey(), 'token' => 'falsch']))->assertNotFound();
        $this->get($url)->assertOk()->assertSee($quote->number);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->post(route('quotes.portal.decide', $quote), [
            'token' => (string) $query['token'],
            'decision' => 'accept',
            'item_ids' => $quote->items()->pluck('id')->all(),
        ])->assertRedirect();
        $this->assertSame('accepted', $quote->fresh()->status);

        // Überführung in eine Entwurfsrechnung.
        $this->actingAs($this->user)->post(route('quotes.convert', $quote))->assertRedirect();
        $invoice = Invoice::query()->where('quote_id', $quote->id)->firstOrFail();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertEqualsWithDelta(180.0, $invoice->subtotal?->toFloat(), 0.01);
    }

    public function test_quote_index_and_dialog_render(): void {
        // Feature 105: die Angebotsliste ist der Angebote-Tab des Belegflusses.
        $this->actingAs($this->user)->get(route('quotes.index'))
            ->assertRedirect(route('billing.feed', ['tab' => 'quotes']));
        $this->actingAs($this->user)->get(route('billing.feed', ['tab' => 'quotes']))->assertOk();
        $this->actingAs($this->user)->get(route('quotes.create'))->assertOk();
    }

    public function test_billing_report_contains_einvoicing_sections(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);

        $this->actingAs($admin)->get(route('reports.billing'))
            ->assertOk()
            ->assertViewHas('einvoicing', function (array $einvoicing): bool {
                return array_key_exists('incoming', $einvoicing)
                    && array_key_exists('validation', $einvoicing)
                    && array_key_exists('dunning', $einvoicing)
                    && array_key_exists('incoming_transferred', $einvoicing);
            });
    }
}
