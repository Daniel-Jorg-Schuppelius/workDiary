<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentLineKindTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Billing\DocumentLineKind;
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Sales\QuoteStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Sales\QuoteItem;
use App\Plugins\Lexoffice\Services\LexofficeInvoiceMapper;
use App\Services\Billing\DocumentOutline;
use App\Services\Invoicing\{InvoiceIssueException, InvoiceIssueService, InvoicePdfRenderer, QuoteService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1054: Titel, Text und Alternativen — Gliederung in Anzeige und PDF,
 * keine Wirkung auf Summen, keine Leerzeilen in E-Rechnung und Übergaben.
 */
class DocumentLineKindTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Gliederung Test']);
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Gliederung Test GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@gliederung.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Gliederung Test GmbH',
            'payment_terms_days' => 14,
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Bauherr GmbH',
            'company' => 'Bauherr GmbH',
            'currency' => 'EUR',
            'email' => 'einkauf@bauherr.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
    }

    /** @param list<array<string, mixed>> $lines */
    private function invoice(array $lines, InvoiceStatus $status = InvoiceStatus::Draft): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'issued_on' => '2026-10-01',
            'due_on' => '2026-10-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $position = 0;
        foreach ($lines as $line) {
            $structure = in_array($line['line_kind'] ?? 'item', ['title', 'text'], true);
            $invoice->items()->create(array_merge([
                'organization_id' => $this->organization->id,
                'unit' => $structure ? '' : 'Std.',
                'quantity' => $structure ? '0' : '1',
                'unit_price' => $structure ? '0' : '100.00',
                'tax_rate' => $structure ? null : '19.00',
                'position' => ++$position,
            ], $line));
        }
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer', 'organization']);
    }

    private function structuredInvoice(InvoiceStatus $status = InvoiceStatus::Draft): Invoice {
        return $this->invoice([
            ['line_kind' => 'title', 'description' => 'Erdgeschoss'],
            ['description' => 'Wände streichen', 'unit_price' => '300.00'],
            ['description' => 'Decken streichen', 'unit_price' => '200.00'],
            ['line_kind' => 'text', 'description' => 'Farbton nach Muster'],
            ['line_kind' => 'title', 'description' => 'Obergeschoss'],
            ['description' => 'Wände streichen', 'unit_price' => '100.00'],
        ], $status);
    }

    public function test_titles_and_text_do_not_change_totals_and_number_the_outline(): void {
        $invoice = $this->structuredInvoice();

        $this->assertSame('600.00', $invoice->subtotal?->getAmount());
        $this->assertSame('114.00', $invoice->tax_amount?->getAmount());
        $this->assertCount(1, $invoice->tax_breakdown);

        $rows = DocumentOutline::rows($invoice->items);
        $this->assertSame(['1', '1.1', '1.2', null, '1', '2', '2.1', '2'], array_map(
            fn (array $row): ?string => $row['number'],
            $rows,
        ));
        $this->assertSame('500.00', $rows[4]['amount']->getAmount());
        $this->assertSame('100.00', $rows[7]['amount']->getAmount());
    }

    public function test_pdf_shows_titles_text_and_subtotals(): void {
        $html = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($this->structuredInvoice()))->render();

        $this->assertStringContainsString('Erdgeschoss', $html);
        $this->assertStringContainsString('Farbton nach Muster', $html);
        $this->assertStringContainsString('Summe Titel 1 Erdgeschoss', $html);
        $this->assertStringContainsString('500,00', $html);
    }

    public function test_xrechnung_carries_only_priced_lines_and_text_as_note(): void {
        $invoice = $this->structuredInvoice(InvoiceStatus::Issued);

        $response = $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice));

        $response->assertOk();
        $xml = (string) $response->getContent();
        $this->assertSame(3, substr_count($xml, '<cac:InvoiceLine>'));
        $this->assertStringNotContainsString('<cbc:Name>Erdgeschoss</cbc:Name>', $xml);
        $this->assertStringContainsString('Farbton nach Muster', $xml);
    }

    public function test_invoice_with_only_titles_counts_as_empty(): void {
        $invoice = $this->invoice([['line_kind' => 'title', 'description' => 'Nur Titel']]);

        $this->expectException(InvoiceIssueException::class);
        app(InvoiceIssueService::class)->assertIssuable($invoice);
    }

    public function test_lexoffice_gets_text_lines_for_structure(): void {
        $payload = app(LexofficeInvoiceMapper::class)->toPayload($this->structuredInvoice(), null);

        $this->assertSame(['text', 'service', 'service', 'text', 'text', 'service'], array_column($payload['lineItems'], 'type'));
    }

    public function test_title_can_be_added_without_quantity_and_price(): void {
        $invoice = $this->invoice([['description' => 'Leistung']]);

        $this->actingAs($this->admin)
            ->post(route('invoices.items.store', $invoice), ['line_kind' => 'title', 'description' => 'Bad'])
            ->assertRedirect();

        $title = $invoice->items()->where('line_kind', 'title')->first();
        $this->assertNotNull($title);
        $this->assertSame('Bad', $title->description);
        $this->assertSame('0.00', $title->amount?->getAmount());
    }

    public function test_quote_alternative_counts_only_when_chosen_and_becomes_item_on_invoice(): void {
        $this->actingAs($this->admin);
        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $this->customer->id], [
            ['line_kind' => 'title', 'description' => 'Fenster', 'quantity' => '0', 'unit_price' => '0'],
            ['description' => 'Kunststofffenster', 'quantity' => '1', 'unit_price' => '1000.00'],
            ['line_kind' => 'alternative', 'description' => 'Holzfenster', 'quantity' => '1', 'unit_price' => '1500.00'],
        ], $this->admin);

        $this->assertSame('1000.00', $quote->subtotal?->getAmount());

        $quotes->approve($quote, $this->admin);
        $quotes->send($quote->fresh(), $this->admin);
        $items = $quote->fresh('items')->items;
        $alternative = $items->first(fn (QuoteItem $i): bool => $i->lineKind() === DocumentLineKind::Alternative);
        $quotes->accept($quote->fresh(), [(int) $alternative->id]);

        $decided = $quote->fresh('items');
        $this->assertSame('1500.00', $decided->subtotal?->getAmount());
        $this->assertSame(QuoteStatus::PartiallyAccepted, $decided->status);
        $this->assertTrue((bool) $decided->items->first()->accepted, 'Titel wandert immer mit.');

        $invoice = $quotes->convertToInvoice($decided, $this->admin);
        $this->assertSame(['title', 'item'], $invoice->items->map(fn ($i): string => $i->lineKind()->value)->all());
        $this->assertSame('1500.00', $invoice->subtotal?->getAmount());
    }
}
