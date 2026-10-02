<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LabourCostDisclosureTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Article\ArticleType;
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\User;
use App\Models\Sales\Quote;
use App\Services\Invoicing\{InvoiceGenerator, InvoicePdfRenderer, QuoteService};
use App\Services\Org\Install\SettingDefaultsInstallStep;
use App\Settings\{SettingScope, SettingsRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1053: Arbeitskosten nach § 35a EStG — Anteil je Position, Ausweis nach
 * Organisationsregel oder Belegüberschreibung, Rechnung über den
 * DocumentTotalsCalculator, Ausgabe in PDF und E-Rechnung.
 */
class LabourCostDisclosureTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $privateCustomer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Malerbetrieb Test']);
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Malerbetrieb Test GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@maler.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Malerbetrieb Test GmbH',
            'payment_terms_days' => 14,
        ]]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->privateCustomer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Erika Mustermann',
            'currency' => 'EUR',
            'email' => 'erika@example.org',
            'address_street' => 'Wohnweg 3',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => 'privat',
            'created_by' => $this->admin->id,
        ]);
    }

    private function setRule(string $rule): void {
        app(SettingsRegistry::class)->set('invoicing.labour_cost_disclosure', $rule, SettingScope::Organization, $this->organization->fresh());
    }

    /** @param list<array<string, mixed>> $items */
    private function invoice(array $items, array $attributes = [], ?Customer $customer = null): Invoice {
        $invoice = Invoice::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => ($customer ?? $this->privateCustomer)->id,
            'number' => 'R2026-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_DRAFT,
            'issued_on' => '2026-10-01',
            'due_on' => '2026-10-15',
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
                'quantity' => '1',
                'tax_rate' => '19.00',
                'position' => ++$position,
            ], $item));
        }
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer', 'organization']);
    }

    public function test_labour_share_follows_document_discount_and_rounds_per_rate(): void {
        $invoice = $this->invoice([
            ['description' => 'Streichen', 'unit_price' => '100.00', 'labour_share_percent' => '100'],
            ['description' => 'Farbe', 'unit_price' => '200.00', 'labour_share_percent' => '0'],
            ['description' => 'Montage inkl. Material', 'unit_price' => '50.00', 'labour_share_percent' => '40'],
            ['description' => 'Sonstiges', 'unit_price' => '10.00'],
        ], ['discount_percent' => '10.00']);

        $costs = $invoice->labourCosts();

        // (100 + 20) netto, 10 % Belegrabatt → 108,00; 19 % → 20,52.
        $this->assertNotNull($costs);
        $this->assertSame('108.00', $costs['net']->getAmount());
        $this->assertSame('20.52', $costs['tax']->getAmount());
        $this->assertSame('128.52', $costs['gross']->getAmount());
        $this->assertSame(1, $costs['undetermined']);
    }

    public function test_reverse_charge_has_no_tax_and_lines_without_share_give_null(): void {
        $none = $this->invoice([['unit_price' => '100.00']]);
        $this->assertNull($none->labourCosts());

        $reverse = $this->invoice([['unit_price' => '100.00', 'labour_share_percent' => '100']], ['is_reverse_charge' => true]);
        $this->assertSame('0.00', $reverse->labourCosts()['tax']->getAmount());
    }

    public function test_new_lines_get_their_share_from_source_and_article(): void {
        $this->assertSame(100, (new InvoiceItem())->forceFill(['time_entry_id' => 1])->defaultLabourShare());
        $this->assertSame(100, (new InvoiceItem())->forceFill(['tour_id' => 1])->defaultLabourShare());
        $this->assertSame(0, (new InvoiceItem())->forceFill(['material_usage_id' => 1])->defaultLabourShare());
        $this->assertNull((new InvoiceItem())->forceFill(['settled_invoice_id' => 1, 'time_entry_id' => 1])->defaultLabourShare());

        $service = Article::factory()->create(['organization_id' => $this->organization->id, 'type' => ArticleType::Service->value]);
        $goods = Article::factory()->create(['organization_id' => $this->organization->id, 'type' => ArticleType::Merchandise->value]);
        $invoice = $this->invoice([
            ['article_id' => $service->id, 'unit_price' => '80.00'],
            ['article_id' => $goods->id, 'unit_price' => '20.00'],
            ['unit_price' => '5.00'],
        ]);

        $this->assertSame(['100.00', '0.00', null], $invoice->items->map(
            fn (InvoiceItem $item): ?string => $item->labour_share_percent?->getNumericValue()
        )->all());
    }

    public function test_rule_private_customers_discloses_only_without_company_and_vat_id(): void {
        $this->setRule('private_customers');
        $business = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Hausverwaltung',
            'company' => 'Hausverwaltung GmbH',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
        $items = [['unit_price' => '100.00', 'labour_share_percent' => '100']];

        $this->assertTrue($this->invoice($items)->disclosesLabourCosts());
        $this->assertFalse($this->invoice($items, [], $business)->disclosesLabourCosts());
        $this->assertTrue($this->invoice($items, ['is_labour_cost_disclosed' => true], $business)->disclosesLabourCosts());
        $this->assertFalse($this->invoice($items, ['is_labour_cost_disclosed' => false])->disclosesLabourCosts());
    }

    public function test_pdf_and_xrechnung_carry_the_disclosure(): void {
        $this->setRule('private_customers');
        $invoice = $this->invoice([
            ['unit_price' => '100.00', 'labour_share_percent' => '100'],
            ['unit_price' => '50.00', 'labour_share_percent' => '0'],
        ], ['status' => Invoice::STATUS_ISSUED]);

        $html = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($invoice))->render();
        $this->assertStringContainsString('§ 35a EStG', $html);
        $this->assertStringContainsString('119,00', $html);

        $response = $this->actingAs($this->admin)->get(route('invoices.einvoice', $invoice));
        $response->assertOk();
        $this->assertStringContainsString('§ 35a EStG: 119,00 EUR', (string) $response->getContent());
    }

    public function test_rule_off_shows_nothing(): void {
        $invoice = $this->invoice([['unit_price' => '100.00', 'labour_share_percent' => '100']]);

        $this->assertNull($invoice->disclosedLabourCosts());
        $html = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($invoice))->render();
        $this->assertStringNotContainsString('§ 35a EStG', $html);
    }

    public function test_cancellation_and_quote_conversion_carry_share_and_override(): void {
        $original = $this->invoice([['unit_price' => '100.00', 'labour_share_percent' => '60']], [
            'status' => Invoice::STATUS_ISSUED,
            'is_labour_cost_disclosed' => true,
        ]);
        $cancellation = app(InvoiceGenerator::class)->cancellationFor($original, 'Test', $this->admin->id);
        $this->assertSame('60.00', $cancellation->items->first()->labour_share_percent?->getNumericValue());
        $this->assertTrue($cancellation->is_labour_cost_disclosed);

        $this->actingAs($this->admin);
        $quotes = app(QuoteService::class);
        $quote = $quotes->create(
            ['customer_id' => $this->privateCustomer->id, 'is_labour_cost_disclosed' => true],
            [['description' => 'Fassade', 'quantity' => '1', 'unit_price' => '1000.00', 'labour_share_percent' => '70']],
            $this->admin,
        );
        $quotes->approve($quote, $this->admin);
        $quotes->send($quote->fresh(), $this->admin);
        $quotes->accept($quote->fresh());
        $invoice = $quotes->convertToInvoice($quote->fresh(), $this->admin);

        $this->assertSame('70.00', $invoice->items->first()->labour_share_percent?->getNumericValue());
        $this->assertTrue($invoice->is_labour_cost_disclosed);
        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertSame('833.00', $quote->fresh('items')->labourCosts()['gross']->getAmount());
    }

    public function test_profile_settings_step_never_overrides_an_own_choice(): void {
        $step = app(SettingDefaultsInstallStep::class);

        $first = $step->install($this->organization->fresh(), ['invoicing.labour_cost_disclosure' => 'private_customers', 'unknown.key' => 'x'], $this->admin);
        $this->assertSame(['created' => 1, 'skipped' => 1], $first);

        $this->setRule('off');
        $second = $step->install($this->organization->fresh(), ['invoicing.labour_cost_disclosure' => 'always'], $this->admin);
        $this->assertSame(['created' => 0, 'skipped' => 1], $second);
        $this->assertSame('off', app(SettingsRegistry::class)->effective('invoicing.labour_cost_disclosure', $this->organization->fresh())->value);
    }
}
