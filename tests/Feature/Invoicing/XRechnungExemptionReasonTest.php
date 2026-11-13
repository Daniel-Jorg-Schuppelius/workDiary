<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungExemptionReasonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\TaxRuleStatus;
use App\Enums\Invoicing\XRechnungSyntax;
use App\Models\Customer\Customer;
use App\Models\Finance\TaxRule;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Accounting\Posting\Adapters\SalesInvoiceAdapter;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService, TaxResolver};
use ERechnungToolkit\Parsers\ERechnungParser;
use ERechnungToolkit\Validators\KositValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623: Befreiungsgrund je Steueraufriss (BG-23) für die Kategorien ohne
 * Steuer — innergemeinschaftliche Lieferung (K), Ausfuhr (G), nicht steuerbar
 * (O, seit MVP-1684 auch die Leistung ins Drittland), steuerfrei (E) nach § 19
 * und nach § 4 UStG. Geprüft über den KoSIT-Validator des php-erechnung-toolkit.
 */
final class XRechnungExemptionReasonTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seller();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    /**
     * MVP-1684: Eine Leistung an ein Unternehmen im Drittland ist im Inland nicht
     * steuerbar (§ 3a Abs. 2 UStG) — Kategorie O in UBL, CII und Buchhaltung aus
     * derselben eingefrorenen Kategorie, ohne USt-IdNrn. (BR-O-02).
     */
    public function test_service_to_a_third_country_is_not_subject_in_einvoice_and_accounting(): void {
        $invoice = $this->issued($this->customer('CH'), [['Wartung Steuerungsanlage', null, null]]);
        $generator = app(XRechnungGenerator::class);
        $this->assertSame('O', data_get($invoice->tax_context, 'category'));

        foreach ([XRechnungSyntax::Ubl, XRechnungSyntax::Cii] as $syntax) {
            $xml = $generator->generate($invoice, $syntax);

            $this->assertSame((string) __('invoicing.einvoice.exemption_not_subject'), $this->subtotal($xml, 'O')->getExemptionReason(), $syntax->value);
            $this->assertStringContainsString('VATEX-EU-O', $xml);
            $this->assertStringNotContainsString('VATEX-EU-G', $xml);
            $document = (new ERechnungParser)->parse($xml);
            $this->assertNull($document->getSeller()->getVatId(), $syntax->value);
            $this->assertNull($document->getBuyer()->getVatId(), $syntax->value);
            $this->assertSame('201/987/65432', $document->getSeller()->getTaxRegistrationId(), $syntax->value);
            $this->assertKositAccepted($xml);
        }
        $this->assertSame([], $generator->preflight($invoice)['errors']);
        $this->assertSame(['category' => 'O', 'domestic' => false], app(SalesInvoiceAdapter::class)->zeroRatedCategory($this->organization, $invoice));
    }

    /** Ohne Steuernummer kennzeichnet nichts den Verkäufer einer nicht steuerbaren Rechnung (BR-O-02): Die Prüfung hält an. */
    public function test_service_to_a_third_country_needs_the_tax_number(): void {
        $settings = (array) $this->organization->refresh()->settings;
        $settings['einvoice']['tax_number'] = '';
        $this->organization->update(['settings' => $settings]);

        $invoice = $this->issued($this->customer('CH'), [['Wartung Steuerungsanlage', null, null]]);

        $this->assertContains((string) __('invoicing.einvoice.error.not_subject_tax_number'), app(XRechnungGenerator::class)->preflight($invoice)['errors']);
    }

    /** Warenausfuhr bleibt G mit dem Grund der Ausfuhrlieferung, auch im Hinweistext des Entwurfs. */
    public function test_goods_export_to_a_third_country_keeps_category_g(): void {
        $customer = $this->customer('CH');
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $draft->update(['category' => Invoice::CATEGORY_MATERIAL]);
        $invoice = $this->issued($customer, [['Steuermodul S7', null, null]], $draft);

        $xml = app(XRechnungGenerator::class)->generate($invoice);

        $this->assertSame('G', data_get($invoice->tax_context, 'category'));
        $this->assertSame((string) __('invoicing.einvoice.exemption_export_goods'), $this->subtotal($xml, 'G')->getExemptionReason());
        $this->assertStringContainsString('VATEX-EU-G', $xml);
        $this->assertKositAccepted($xml);
        $this->assertSame(['category' => 'G', 'domestic' => false], app(SalesInvoiceAdapter::class)->zeroRatedCategory($this->organization, $invoice));
        $this->assertSame((string) config('taxation.notes.export_goods'), app(TaxResolver::class)->resolve($this->organization, $customer, category: 'goods')['note']);
    }

    public function test_intra_community_supply_carries_the_ic_reason_and_buyer_vat_id(): void {
        $customer = $this->customer('AT', 'ATU12345675');
        $invoice = $this->issued($customer, [['Steuermodul S7', 'K', '0.00']]);
        $generator = app(XRechnungGenerator::class);

        $xml = $generator->generate($invoice);

        $subtotal = $this->subtotal($xml, 'K');
        $this->assertStringContainsString('VATEX-EU-IC', $xml);
        $this->assertSame((string) __('invoicing.einvoice.exemption_intra_community'), $subtotal->getExemptionReason());
        $this->assertSame([], $generator->preflight($invoice)['errors']);
        $this->assertKositAccepted($xml);

        // Die ausgestellte Rechnung behält die eingefrorene USt-IdNr. (MVP-1624); fehlt sie beim Ausstellen, hält die Prüfung an.
        $customer->update(['vat_id' => null]);
        $this->assertNotContains((string) __('invoicing.einvoice.error.intra_community_buyer_vat_id'), $generator->preflight($invoice->refresh())['errors']);
        $withoutVatId = $this->issued($customer->refresh(), [['Steuermodul S7', 'K', '0.00']]);
        $this->assertContains((string) __('invoicing.einvoice.error.intra_community_buyer_vat_id'), $generator->preflight($withoutVatId)['errors']);
    }

    /** MVP-1686: Lieferanschrift mit Lieferland (BG-15, BT-80) aus dem Empfänger, in UBL und CII (BR-IC-12). */
    public function test_intra_community_supply_names_the_delivery_country_of_the_recipient(): void {
        $invoice = $this->issued($this->customer('AT', 'ATU12345675'), [['Steuermodul S7', 'K', '0.00']]);
        $generator = app(XRechnungGenerator::class);

        foreach ([XRechnungSyntax::Ubl, XRechnungSyntax::Cii] as $syntax) {
            $xml = $generator->generate($invoice, $syntax);

            $address = (new ERechnungParser)->parse($xml)->getDeliveryParty()?->getPostalAddress();
            $this->assertSame('AT', $address?->getCountryCode(), $syntax->value);
            $this->assertSame('Kundenstadt', $address->getCity(), $syntax->value);
            $this->assertSame('54321', $address->getPostalCode(), $syntax->value);
            $this->assertKositAccepted($xml);
        }
        // Nur bei K: Andere Kategorien bleiben ohne Lieferpartei.
        $domestic = $this->issued($this->customer('DE'), [['Montage', null, null]]);
        $this->assertNull((new ERechnungParser)->parse($generator->generate($domestic))->getDeliveryParty());
    }

    /** BR-DE-10/11: Die Lieferanschrift der XRechnung braucht Ort und PLZ; ZUGFeRD kommt ohne aus. */
    public function test_intra_community_supply_without_city_fails_the_xrechnung_preflight(): void {
        $customer = $this->customer('AT', 'ATU12345675');
        $customer->update(['address_city' => null, 'address_zip' => null]);
        $invoice = $this->issued($customer->refresh(), [['Steuermodul S7', 'K', '0.00']]);
        $generator = app(XRechnungGenerator::class);
        $message = (string) __('invoicing.einvoice.error.intra_community_delivery_address');

        $this->assertContains($message, $generator->preflight($invoice)['errors']);
        $this->assertNotContains($message, $generator->preflight($invoice, \ERechnungToolkit\Enums\ERechnungProfile::EN16931)['errors']);
    }

    public function test_not_subject_supply_stands_alone_without_vat_ids(): void {
        $invoice = $this->issued($this->customer('DE', 'DE811569869'), [['Beratung Standort Zürich', 'O', '0.00']]);

        $xml = app(XRechnungGenerator::class)->generate($invoice);

        $subtotal = $this->subtotal($xml, 'O');
        $this->assertStringContainsString('VATEX-EU-O', $xml);
        $document = (new ERechnungParser)->parse($xml);
        // BR-O-02: keine USt-IdNr. von Verkäufer und Käufer; die Steuernummer kennzeichnet den Verkäufer.
        $this->assertNull($document->getSeller()->getVatId());
        $this->assertNull($document->getBuyer()->getVatId());
        $this->assertSame('201/987/65432', $document->getSeller()->getTaxRegistrationId());
        // BR-O-05: kein Steuersatz an der Position (BT-152) bei „nicht steuerbar“ (php-erechnung-toolkit 0.22).
        $this->assertStringNotContainsString('<cbc:Percent>', (string) strstr($xml, '<cac:InvoiceLine>'));
        $this->assertKositAccepted($xml);
    }

    public function test_not_subject_next_to_another_category_fails_the_preflight(): void {
        $invoice = $this->issued($this->customer('DE'), [['Montage', null, null], ['Beratung Standort Zürich', 'O', '0.00']]);

        $this->assertContains((string) __('invoicing.einvoice.error.not_subject_mixed'), app(XRechnungGenerator::class)->preflight($invoice)['errors']);
    }

    public function test_small_business_keeps_the_section_19_reason(): void {
        $this->seller(vatId: '', smallBusiness: true);
        $invoice = $this->issued($this->customer('DE'), [['Reparatur Heizungssteuerung', null, null]]);

        $xml = app(XRechnungGenerator::class)->generate($invoice);

        $subtotal = $this->subtotal($xml, 'E');
        $this->assertSame((string) __('invoicing.einvoice.exemption_small_business'), $subtotal->getExemptionReason());
        $this->assertKositAccepted($xml);
    }

    /** § 4 UStG neben dem Regelsatz: zwei Aufrisse, nur der steuerfreie trägt einen Grund — der aus der Steuerregel, nicht § 19. */
    public function test_tax_exempt_items_take_the_reason_from_the_exempt_tax_rule_per_breakdown(): void {
        TaxRule::query()->create([
            'organization_id' => $this->organization->id,
            'country' => 'DE',
            'category' => 'services',
            'rate_type' => 'exempt',
            'rate' => '0.00',
            'valid_from' => '2026-01-01',
            'note' => 'Steuerfreie Heilbehandlung (§ 4 Nr. 14 Buchst. a UStG)',
            'status' => TaxRuleStatus::Active,
        ]);
        $invoice = $this->issued($this->customer('DE'), [['Praxisorganisation', null, null], ['Physiotherapie', 'E', '0.00']]);
        $generator = app(XRechnungGenerator::class);
        $this->assertSame([], $generator->preflight($invoice)['errors']);
        $this->assertNotContains((string) __('invoicing.einvoice.warning.exemption_unspecified'), $generator->preflight($invoice)['warnings']);

        foreach ([XRechnungSyntax::Ubl, XRechnungSyntax::Cii] as $syntax) {
            $xml = $generator->generate($invoice, $syntax);

            $this->assertSame('Steuerfreie Heilbehandlung (§ 4 Nr. 14 Buchst. a UStG)', $this->subtotal($xml, 'E')->getExemptionReason(), $syntax->value);
            $this->assertNull($this->subtotal($xml, 'S')->getExemptionReason(), $syntax->value);
            $this->assertStringNotContainsString('§ 19', $xml);
            $this->assertKositAccepted($xml);
        }
    }

    public function test_tax_exempt_items_without_a_rule_say_tax_free_and_warn(): void {
        $invoice = $this->issued($this->customer('DE'), [['Physiotherapie', 'E', '0.00']]);
        $generator = app(XRechnungGenerator::class);

        $this->assertContains((string) __('invoicing.einvoice.warning.exemption_unspecified'), $generator->preflight($invoice)['warnings']);
        $this->assertSame((string) __('invoicing.einvoice.exemption_tax_free'), $this->subtotal($generator->generate($invoice), 'E')->getExemptionReason());
    }

    /** Rechnet der Beleg Steuer auf eine Position ohne Steuer, wichen Rechnung und E-Rechnung ab. */
    public function test_tax_on_a_category_without_tax_fails_the_preflight(): void {
        $invoice = $this->issued($this->customer('DE'), [['Physiotherapie', 'E', null]]);

        $errors = app(XRechnungGenerator::class)->preflight($invoice)['errors'];

        $this->assertContains((string) __('invoicing.einvoice.error.line_category_rate', ['line' => 'Physiotherapie', 'category' => 'E', 'rate' => '19,00']), $errors);
    }

    private function seller(string $vatId = 'DE136695976', bool $smallBusiness = false): void {
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispieltechnik GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => $vatId,
            'tax_number' => '201/987/65432',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@beispieltechnik.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Beispieltechnik GmbH',
            'small_business' => $smallBusiness ? '1' : '0',
        ]]]);
    }

    private function customer(string $country, ?string $vatId = null): Customer {
        return Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde Beispiel ' . $country,
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Kundenstadt',
            'country' => $country,
            'vat_id' => $vatId,
            'buyer_reference' => '991-12345-73',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Entwurf über den TaxResolver, ausgestellt.
     *
     * @param list<array{0: string, 1: ?string, 2: ?string}> $lines Beschreibung, Steuerkategorie, Steuersatz
     */
    private function issued(Customer $customer, array $lines, ?Invoice $draft = null): Invoice {
        $invoice = $draft ?? app(InvoiceGenerator::class)->emptyDraft($customer);
        foreach ($lines as $i => [$description, $category, $rate]) {
            $invoice->items()->create([
                'organization_id' => $this->organization->id,
                'description' => $description,
                'quantity' => '2',
                'unit' => 'Std.',
                'unit_price' => '80.00',
                'tax_category' => $category,
                'tax_rate' => $rate,
                'service_date' => '2026-10-15',
                'position' => $i + 1,
            ]);
        }
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return app(InvoiceIssueService::class)->issue($invoice->refresh())->refresh();
    }

    private function subtotal(string $xml, string $category): \ERechnungToolkit\Entities\TaxSubtotal {
        foreach ((new ERechnungParser)->parse($xml)->getTaxTotal()?->getSubtotals() ?? [] as $subtotal) {
            if ($subtotal->getCategory()->value === $category) {
                return $subtotal;
            }
        }
        $this->fail('Kein Steueraufriss der Kategorie ' . $category);
    }

    private function assertKositAccepted(string $xml): void {
        $kosit = new KositValidator;
        if (! $kosit->isAvailable()) {
            $this->markTestSkipped('KoSIT-Validator (Java + JAR) ist nicht verfügbar.');
        }
        $result = $kosit->validate($xml);
        $this->assertSame([], array_map('strval', $result->getErrors()));
        $this->assertTrue($result->isAccepted());
    }
}
