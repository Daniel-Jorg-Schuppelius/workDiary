<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntraCommunitySupplyTaxCaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{InvoiceStatus, SalesTaxCase};
use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\{TimesheetKind, TimesheetStatus};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Material\MaterialUsage;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\Timesheet;
use App\Services\Accounting\Posting\Adapters\SalesInvoiceAdapter;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueException, InvoiceIssueService, InvoicePdfRenderer, OutgoingTaxCaseResolver, TaxResolver};
use ERechnungToolkit\Parsers\ERechnungParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1731: Waren an ein EU-Unternehmen mit gültiger USt-IdNr. sind eine
 * innergemeinschaftliche Lieferung (§ 4 Nr. 1 Buchst. b, § 6a UStG):
 * Kategorie `K` ohne Reverse-Charge-Merkmal, eigener Hinweis, E-Rechnung mit
 * Lieferland (BT-80) und Lieferdatum; Buchhaltung, Kennziffern und
 * Buchhaltungsprogramme wie bisher. Vorher fror der `TaxResolver` `AE` mit
 * Reverse-Charge-Merkmal ein. Leistungen an dasselbe Unternehmen bleiben `AE`.
 */
final class IntraCommunitySupplyTaxCaseTest extends TestCase {
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

    public function test_material_invoice_to_an_eu_business_is_an_intra_community_supply(): void {
        $customer = $this->euBusiness();
        $draft = $this->materialDraft($customer);

        $this->assertFalse((bool) $draft->is_reverse_charge, 'Keine Steuerschuld des Empfängers.');
        $this->assertTrue($draft->tax_rate?->isZero());
        $this->assertStringContainsString((string) config('taxation.notes.intra_community'), (string) $draft->notes);
        $this->assertStringNotContainsString((string) config('taxation.notes.reverse_charge'), (string) $draft->notes);

        $issued = app(InvoiceIssueService::class)->issue($draft)->refresh();

        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertSame('K', data_get($issued->tax_context, 'category'));
        $this->assertFalse((bool) data_get($issued->tax_context, 'is_reverse_charge'));
        $this->assertFalse((bool) $issued->is_reverse_charge);

        // Buchhaltung, DATEV und Buchhaltungsprogramme lesen denselben Fall wie bisher bei AE an Waren.
        $this->assertSame(['category' => 'K', 'domestic' => false], app(SalesInvoiceAdapter::class)->zeroRatedCategory($this->organization, $issued));
        $this->assertSame([SalesTaxCase::IntraCommunitySupply], array_map(static fn ($case): SalesTaxCase => $case->case, app(OutgoingTaxCaseResolver::class)->forInvoice($issued)));

        // Das PDF druckt keinen Reverse-Charge-Satz und den Hinweis nicht doppelt (er steht im Rechnungstext).
        $this->assertSame([], app(InvoicePdfRenderer::class)->viewData($issued->load(['items', 'customer', 'organization']))['taxNotes']);
        $html = app(InvoicePdfRenderer::class)->composedHtml($issued);
        $this->assertStringNotContainsString(e((string) __('Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).')), $html);
    }

    public function test_e_invoice_carries_category_k_with_delivery_country_and_date(): void {
        $issued = app(InvoiceIssueService::class)->issue($this->materialDraft($this->euBusiness()))->refresh();
        $generator = app(XRechnungGenerator::class);

        $this->assertSame([], $generator->preflight($issued)['errors']);
        $xml = $generator->generate($issued);
        $xp = $this->xpath($xml);

        $this->assertSame('K', $xp->evaluate(self::TAX_CATEGORY));
        $this->assertStringContainsString('VATEX-EU-IC', $xml);
        $this->assertStringNotContainsString('VATEX-EU-AE', $xml);
        $this->assertSame('AT', (new ERechnungParser)->parse($xml)->getDeliveryParty()?->getPostalAddress()?->getCountryCode(), 'BT-80 (BR-IC-12)');
        $this->assertSame('2030-04-02', $xp->evaluate('string(//cac:Delivery/cbc:ActualDeliveryDate)'), 'BT-72 (BR-IC-11)');
        $this->assertSame((string) __('invoicing.einvoice.exemption_intra_community'), $generator->exemptionReasons($issued)['K'] ?? null);
    }

    /** Storno und Gutschrift übernehmen den Fall des Originals: K ohne Reverse-Charge-Merkmal. */
    public function test_corrections_take_the_intra_community_case_of_the_original(): void {
        $original = app(InvoiceIssueService::class)->issue($this->materialDraft($this->euBusiness()))->refresh();

        $cancellation = app(InvoiceGenerator::class)->cancellationFor($original, null, $this->admin->id);

        $this->assertFalse((bool) $cancellation->is_reverse_charge);
        $this->assertSame(['K'], array_column(app(TaxResolver::class)->casesToFreeze($cancellation), 'category'));
        $issued = app(InvoiceIssueService::class)->issue($cancellation)->refresh();
        $this->assertSame('K', data_get($issued->tax_context, 'category'));
    }

    /**
     * Ein Entwurf aus der Zeit vor MVP-1731 (AE mit Reverse-Charge-Merkmal und
     * dessen Hinweis) zieht beim Stellen auf K nach und hält einmal an.
     */
    public function test_a_legacy_draft_is_aligned_to_k_on_issue(): void {
        $draft = $this->materialDraft($this->euBusiness());
        $draft->forceFill([
            'is_reverse_charge' => true,
            'notes' => (string) config('taxation.notes.reverse_charge'),
        ])->save();

        try {
            app(InvoiceIssueService::class)->issue($draft->refresh());
            $this->fail('Der geänderte Steuerfall hätte das Stellen anhalten müssen.');
        } catch (InvoiceIssueException $e) {
            $this->assertSame(InvoiceIssueException::REASON_TAX_CASE_CHANGED, $e->reason);
        }

        $draft->refresh();
        $this->assertFalse((bool) $draft->is_reverse_charge);
        $this->assertSame((string) config('taxation.notes.intra_community'), $draft->notes, 'Der automatische Hinweis des alten Falls ist ersetzt.');
        $this->assertSame('K', data_get(app(InvoiceIssueService::class)->issue($draft)->refresh()->tax_context, 'category'));
    }

    /** Ausgestellte Altbelege mit AE an Waren bleiben, wie sie sind. */
    public function test_issued_legacy_invoice_keeps_its_frozen_reverse_charge(): void {
        $customer = $this->euBusiness();
        $issued = app(InvoiceIssueService::class)->issue($this->materialDraft($customer))->refresh();
        // Stand vor MVP-1731, am Sperr-Guard ausgestellter Belege vorbei geschrieben.
        Invoice::query()->whereKey($issued->id)->update([
            'is_reverse_charge' => true,
            'tax_context' => json_encode(['category' => 'AE', 'is_reverse_charge' => true] + (array) $issued->tax_context),
        ]);
        $issued = Invoice::query()->with(['items', 'customer'])->findOrFail($issued->id);

        $this->assertSame('AE', $this->xpath(app(XRechnungGenerator::class)->generate($issued))->evaluate(self::TAX_CATEGORY));
        $this->assertSame(SalesTaxCase::IntraCommunitySupply, app(OutgoingTaxCaseResolver::class)->forInvoice($issued)[0]->case);
    }

    /** Gegenprobe: Eine Leistung an dasselbe Unternehmen bleibt sonstige Leistung mit Reverse Charge (AE). */
    public function test_services_to_an_eu_business_stay_reverse_charge(): void {
        $customer = $this->euBusiness();

        $tax = app(TaxResolver::class)->resolve($this->organization, $customer);
        $this->assertSame(['AE', true, '0.00'], [$tax['category'], $tax['reverse_charge'], $tax['rate']]);
        $this->assertSame((string) config('taxation.notes.reverse_charge'), $tax['note']);

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
        $issued = app(InvoiceIssueService::class)->issue($draft->refresh())->refresh();

        $this->assertSame('AE', data_get($issued->tax_context, 'category'));
        $this->assertTrue((bool) $issued->is_reverse_charge);
        $this->assertSame(SalesTaxCase::EuReverseCharge, app(OutgoingTaxCaseResolver::class)->forInvoice($issued)[0]->case);
    }

    /** Eine Steuerregel der Satzart „innergemeinschaftliche Lieferung“ liefert den Hinweis, sonst gilt der Katalogtext. */
    public function test_an_organization_rule_supplies_the_note(): void {
        \App\Models\Finance\TaxRule::query()->create([
            'organization_id' => $this->organization->id,
            'country' => 'DE',
            'category' => 'goods',
            'rate_type' => 'intra_community',
            'rate' => '0.00',
            'valid_from' => '2020-01-01',
            'source' => '§ 6a UStG',
            'note' => 'Innergemeinschaftliche Lieferung, steuerfrei nach § 4 Nr. 1b UStG.',
            'status' => \App\Enums\Finance\TaxRuleStatus::Active,
        ]);

        $tax = app(TaxResolver::class)->resolve($this->organization, $this->euBusiness(), category: 'goods');

        $this->assertSame(['K', false], [$tax['category'], $tax['reverse_charge']]);
        $this->assertSame('Innergemeinschaftliche Lieferung, steuerfrei nach § 4 Nr. 1b UStG.', $tax['note']);
        $this->assertNotNull($tax['rule']);
    }

    private function euBusiness(): Customer {
        return Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Beispiel ' . fake()->unique()->numberBetween(1, 99999),
            'company' => 'Kunde Beispiel GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '4020',
            'address_city' => 'Linz',
            'country' => 'AT',
            'vat_id' => 'ATU13585627',
            'is_consumer' => false,
            'buyer_reference' => '991-12345-73',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }

    private function materialDraft(Customer $customer): Invoice {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Lieferung Steuermodule',
            'status' => ProjectStatus::Active->value,
            'is_default' => false,
        ]);
        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'project_id' => $project->id,
            'work_date' => '2030-04-02',
            'kind' => TimesheetKind::Project,
            'status' => TimesheetStatus::Draft,
        ]);
        MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $timesheet->id,
            'description' => 'Steuermodul S7',
            'quantity' => '3.000',
            'unit' => 'Stk',
            'unit_price' => '120.0000',
            'billed' => false,
        ]);

        return app(InvoiceGenerator::class)->fromMaterialUsages($customer, $project)->refresh();
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
}
