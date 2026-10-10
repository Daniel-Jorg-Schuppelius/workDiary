<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentChainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Dashboard\Widgets\DocumentChainWidget;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Billing\DocumentChainService;
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1057: Belegkette — Quellen aus Vertrieb und Faktura, Seite und Kachel. */
class DocumentChainTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Kette Test']);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        $this->customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Kunde A', 'currency' => 'EUR', 'created_by' => $this->admin->id]);
    }

    /** @return array<string, int> */
    private function counts(): array {
        return collect(app(DocumentChainService::class)->groups($this->organization->fresh(), $this->admin, 5))
            ->mapWithKeys(fn (array $g): array => [$g['key'] => $g['count']])->all();
    }

    public function test_accepted_quote_counts_until_it_is_invoiced(): void {
        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $this->customer->id], [['description' => 'Leistung', 'quantity' => '1', 'unit_price' => '100.00']], $this->admin);
        $quotes->approve($quote, $this->admin);
        $quotes->send($quote->fresh(), $this->admin);
        $quotes->accept($quote->fresh());

        $this->assertSame(1, $this->counts()['quotes_to_invoice']);

        $quotes->convertToInvoice($quote->fresh(), $this->admin);
        $this->assertSame(0, $this->counts()['quotes_to_invoice']);
    }

    public function test_overdue_invoice_and_due_follow_up_appear(): void {
        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-OVERDUE-1',
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE,
            'issued_on' => now()->subDays(40)->toDateString(),
            'due_on' => now()->subDays(10)->toDateString(),
            'currency' => 'EUR',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ]);
        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $this->customer->id, 'follow_up_at' => now()->subDay()->toDateString()], [['description' => 'X', 'quantity' => '1', 'unit_price' => '10.00']], $this->admin);
        $quotes->approve($quote, $this->admin);

        $counts = $this->counts();
        $this->assertSame(1, $counts['invoices_overdue']);
        $this->assertSame(1, $counts['quotes_follow_up']);
    }

    /** MVP-1124: im Buchhaltungsprogramm abgerechnete Angebote verlassen die Gruppe, aufhebbar. */
    public function test_quote_marked_as_invoiced_externally_leaves_the_group(): void {
        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $this->customer->id], [['description' => 'Leistung', 'quantity' => '1', 'unit_price' => '100.00']], $this->admin);
        $quotes->approve($quote, $this->admin);
        $quotes->send($quote->fresh(), $this->admin);
        $quotes->accept($quote->fresh());
        $this->assertSame(1, $this->counts()['quotes_to_invoice']);

        $this->get(route('quotes.external-invoice.form', $quote))->assertOk()->assertSee(__('quotes.external_invoice.number'));
        $this->post(route('quotes.external-invoice.store', $quote), ['external_invoice_number' => 'LX-4711'])->assertRedirect();
        $this->assertSame('LX-4711', $quote->fresh()?->external_invoice_number);
        $this->assertSame(0, $this->counts()['quotes_to_invoice']);
        $this->get(route('quotes.show', $quote))->assertOk()->assertSee('LX-4711');

        $this->delete(route('quotes.external-invoice.destroy', $quote))->assertRedirect();
        $this->assertNull($quote->fresh()?->invoiced_externally_at);
        $this->assertSame(1, $this->counts()['quotes_to_invoice']);

        $quotes->convertToInvoice($quote->fresh(), $this->admin);
        $this->get(route('quotes.external-invoice.form', $quote))->assertStatus(409);
    }

    /** MVP-1116: überfällige Belege des Buchhaltungsprogramms zählen mit, offene ohne Fälligkeit nicht. */
    public function test_overdue_mirrored_invoices_join_the_overdue_group(): void {
        foreach ([['LX-OVERDUE', now()->subDays(5)], ['LX-LATER', now()->addDays(5)]] as [$number, $due]) {
            \App\Plugins\Lexoffice\Models\LexofficeVoucher::query()->create([
                'organization_id' => $this->organization->id, 'external_id' => 'ext-' . $number, 'contact_external_id' => 'contact-new',
                'contact_name' => 'Neukunde GmbH', 'voucher_type' => 'invoice', 'voucher_status' => 'open', 'voucher_number' => $number,
                'voucher_date' => now()->subDays(30)->toDateString(), 'due_date' => $due->toDateString(), 'total_amount' => '119.00', 'currency' => 'EUR',
            ]);
        }

        $group = collect(app(DocumentChainService::class)->groups($this->organization->fresh(), $this->admin, 5))->firstWhere('key', 'invoices_overdue');

        $this->assertSame(1, $group['count']);
        $this->assertStringContainsString('LX-OVERDUE', $group['items'][0]->title);
        $this->assertStringContainsString('Neukunde GmbH', $group['items'][0]->title);
        $this->assertStringContainsString('q=LX-OVERDUE', $group['items'][0]->url);
    }

    public function test_page_and_widget_render(): void {
        $this->get(route('billing.chain'))->assertOk()->assertSee(__('invoicing.chain.title'));

        $html = (string) app(DocumentChainWidget::class)->render($this->admin);
        $this->assertStringContainsString(__('invoicing.chain.title'), $html);
    }
}
