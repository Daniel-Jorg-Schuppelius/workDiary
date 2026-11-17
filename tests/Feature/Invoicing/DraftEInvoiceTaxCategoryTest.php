<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DraftEInvoiceTaxCategoryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Article\ArticleType;
use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{InvoiceStatus, PlaceOfSupplyKind, SalesTaxCase};
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService, OutgoingTaxCaseResolver, TaxResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1720: Die E-Rechnungsprüfung eines Entwurfs nimmt die Kategorie, die
 * das Ausstellen einfrieren wird ({@see TaxResolver::casesToFreeze()}).
 * Vorher las der Generator nur den eingefrorenen Fall und fiel beim Entwurf
 * mit 0 % auf `Z` zurück, auch wenn der Fall `O` oder `AE` war. Ausgestellte
 * Belege behalten ihren eingefrorenen Fall.
 */
final class DraftEInvoiceTaxCategoryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string TAX_CATEGORY = 'string(/ubl:Invoice/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:ID)';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispieltechnik GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE136695976',
            'tax_number' => '201/987/65432',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@beispieltechnik.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Beispieltechnik GmbH',
        ]]]);
        $this->admin = $this->orgAdmin(['hourly_rate' => null]);
        $this->actingAs($this->admin);
    }

    public function test_draft_for_a_business_abroad_is_checked_as_not_subject(): void {
        $draft = $this->draftWithLine($this->customer(['country' => 'CH', 'is_consumer' => false]));
        $this->assertTrue($draft->tax_rate?->isZero());

        $xml = app(XRechnungGenerator::class)->generate($this->asSent($draft));

        $this->assertSame('O', $this->xpath($xml)->evaluate(self::TAX_CATEGORY), 'Vorher: Z (Nullsatz).');
        $this->assertStringContainsString('VATEX-EU-O', $xml);
        $this->assertArrayHasKey('O', app(XRechnungGenerator::class)->exemptionReasons($draft->refresh()));
    }

    public function test_draft_follows_the_reverse_charge_case_of_the_customer(): void {
        $customer = $this->customer(['country' => 'DE', 'is_consumer' => false]);
        $draft = $this->draftWithLine($customer);
        $this->assertSame('19.00', $draft->tax_rate?->getNumericValue());
        // Erst nach der Anlage wird der Kunde Leistungsempfänger nach § 13b: das Ausstellen friert AE ein.
        $customer->update(['is_reverse_charge_recipient' => true]);
        $draft->refresh();

        $errors = app(XRechnungGenerator::class)->preflight($this->asSent($draft))['errors'];

        $this->assertContains((string) __('invoicing.einvoice.error.line_category_rate', ['line' => 'Beratung', 'category' => 'AE', 'rate' => '19,00']), $errors);
    }

    public function test_reverse_charge_draft_generates_category_ae(): void {
        $draft = $this->draftWithLine($this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627', 'is_consumer' => false]));

        $xml = app(XRechnungGenerator::class)->generate($this->asSent($draft));

        $this->assertSame('AE', $this->xpath($xml)->evaluate(self::TAX_CATEGORY));
    }

    public function test_domestic_draft_stays_standard_rated(): void {
        $draft = $this->draftWithLine($this->customer(['country' => 'DE', 'is_consumer' => false]));

        $xml = app(XRechnungGenerator::class)->generate($this->asSent($draft));

        $this->assertSame('S', $this->xpath($xml)->evaluate(self::TAX_CATEGORY));
    }

    /** Die Prüfung des gespeicherten Entwurfs (Validierungsseite) nennt den zu teilenden Beleg, statt still einen Fall zu nehmen. */
    public function test_mixed_cases_are_reported_on_the_stored_draft(): void {
        // Verbraucher im Drittland: Katalogleistung nicht steuerbar (O), übrige Leistung mit deutscher Steuer (S).
        $draft = app(InvoiceGenerator::class)->emptyDraft($this->customer(['country' => 'CH', 'is_consumer' => true]));
        foreach ([PlaceOfSupplyKind::Catalog, PlaceOfSupplyKind::General] as $position => $kind) {
            $article = Article::factory()->create([
                'organization_id' => $this->organization->id,
                'type' => ArticleType::Service->value,
                'base_unit' => 'Std.',
                'place_of_supply_kind' => $kind->value,
            ]);
            $draft->items()->create([
                'organization_id' => $this->organization->id,
                'article_id' => $article->id,
                'description' => $article->name,
                'quantity' => '2',
                'unit' => 'Std.',
                'unit_price' => '80.00',
                'service_date' => '2030-04-15',
                'position' => $position + 1,
            ]);
        }
        $draft->refresh()->load(['items', 'customer', 'organization']);
        $this->assertSame(['O', 'S'], array_column(app(TaxResolver::class)->casesToFreeze($draft), 'category'));

        $errors = app(XRechnungGenerator::class)->preflight($draft)['errors'];

        $this->assertContains((string) __('invoicing.einvoice.error.status'), $errors);
        $this->assertContains((string) __('invoicing.einvoice.error.not_subject_mixed'), $errors);
        $this->assertArrayNotHasKey('O', app(XRechnungGenerator::class)->exemptionReasons($draft), 'Kein Fall wird still gewählt.');
    }

    /** Gegenprobe: der eingefrorene Fall gewinnt, auch wenn der Kunde danach umzieht. */
    public function test_issued_invoice_keeps_its_frozen_category(): void {
        $customer = $this->customer(['country' => 'DE', 'is_consumer' => false]);
        $issued = app(InvoiceIssueService::class)->issue($this->draftWithLine($customer))->refresh();
        $this->assertSame('S', data_get($issued->tax_context, 'category'));

        $customer->update(['country' => 'CH']);
        $issued->refresh()->load(['items', 'customer']);

        $this->assertSame('S', $this->xpath(app(XRechnungGenerator::class)->generate($issued))->evaluate(self::TAX_CATEGORY));
    }

    /** Gutschrift: der Fall des Originals, wie das Ausstellen ihn einfriert — dieselbe Quelle wie der Buchhaltungsweg. */
    public function test_credit_note_draft_takes_the_case_of_its_original(): void {
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => false]);
        $original = app(InvoiceIssueService::class)->issue($this->draftWithLine($customer))->refresh();
        $this->assertSame('O', data_get($original->tax_context, 'category'));
        $customer->update(['country' => 'DE']);

        $credit = Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'G-ENTWURF-1',
            'status' => InvoiceStatus::Draft,
            'type' => Invoice::TYPE_CREDIT_NOTE,
            'parent_invoice_id' => $original->id,
            'currency' => 'EUR',
            'tax_rate' => '0.00',
            'created_by' => $this->admin->id,
        ]);

        $this->assertSame(['O'], array_column(app(TaxResolver::class)->casesToFreeze($credit), 'category'));
        $this->assertSame(SalesTaxCase::NotTaxable, app(OutgoingTaxCaseResolver::class)->forInvoice($credit)[0]->case);
    }

    private function draftWithLine(Customer $customer): Invoice {
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $draft->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Beratung',
            'quantity' => '2',
            'unit' => 'Std.',
            'unit_price' => '80.00',
            'service_date' => '2030-04-15',
            'position' => 1,
        ]);
        $draft->load('items');
        $draft->recalculate();
        $draft->save();

        return $draft->refresh();
    }

    /** Der Entwurf als ausgestellt im Speicher, wie im Versandweg — ohne eingefrorenen Fall. */
    private function asSent(Invoice $draft): Invoice {
        $probe = clone $draft->load(['items', 'customer']);
        $probe->status = InvoiceStatus::Issued;
        $probe->issued_on = now();

        return $probe;
    }

    private function xpath(string $xml): \DOMXPath {
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        return $xp;
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde ' . fake()->unique()->numberBetween(1, 99999),
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '8001',
            'address_city' => 'Kundenstadt',
            'buyer_reference' => '991-12345-73',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }
}
