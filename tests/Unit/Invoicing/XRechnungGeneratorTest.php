<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : XRechnungGeneratorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Invoicing;

use App\Models\{Customer, Invoice, User};
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * E-Rechnung (Feature 045, Abschnitt 8): UBL-2.1-XML (XRechnung 3.0) und
 * Pflichtfeld-Preflight aus den echten Invoice-/Customer-/Org-Feldern.
 */
class XRechnungGeneratorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'WorkDiary Org']);
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings()]]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'vat_id' => 'DE987654321',
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function sellerSettings(array $overrides = []): array {
        return array_replace([
            'seller_name' => 'WorkDiary GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'tax_number' => '12/345/67890',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@workdiary.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'WorkDiary GmbH',
            'payment_terms_days' => 30,
            'small_business' => '0',
        ], $overrides);
    }

    private function makeIssuedInvoice(string $taxRate = '19.00', string $type = Invoice::TYPE_INVOICE): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-0042',
            'status' => Invoice::STATUS_ISSUED,
            'type' => $type,
            'issued_on' => '2026-06-01',
            'due_on' => '2026-07-01',
            'currency' => 'EUR',
            'tax_rate' => $taxRate,
            'created_by' => $this->admin->id,
        ]);

        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'service_date' => '2026-05-20',
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'position' => 1,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'service_date' => '2026-05-21',
            'description' => 'Anfahrt',
            'quantity' => '1.00',
            'unit' => 'Pauschale',
            'unit_price' => '50.00',
            'position' => 2,
        ]);

        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer']);
    }

    private function xpath(string $xml): \DOMXPath {
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml), 'XML muss wohlgeformt sein');
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        return $xp;
    }

    public function test_generates_wellformed_ubl_with_required_header_fields(): void {
        $invoice = $this->makeIssuedInvoice();
        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $this->assertSame(XRechnungGenerator::CUSTOMIZATION_ID, $xp->evaluate('string(/ubl:Invoice/cbc:CustomizationID)'));
        $this->assertSame(XRechnungGenerator::PROFILE_ID, $xp->evaluate('string(/ubl:Invoice/cbc:ProfileID)'));
        $this->assertSame('R2026-0042', $xp->evaluate('string(/ubl:Invoice/cbc:ID)'));
        $this->assertSame('2026-06-01', $xp->evaluate('string(/ubl:Invoice/cbc:IssueDate)'));
        $this->assertSame('2026-07-01', $xp->evaluate('string(/ubl:Invoice/cbc:DueDate)'));
        $this->assertSame('380', $xp->evaluate('string(/ubl:Invoice/cbc:InvoiceTypeCode)'));
        $this->assertSame('EUR', $xp->evaluate('string(/ubl:Invoice/cbc:DocumentCurrencyCode)'));
        // BT-10: Käuferreferenz/Leitweg-ID.
        $this->assertSame('991-12345-67', $xp->evaluate('string(/ubl:Invoice/cbc:BuyerReference)'));
    }

    public function test_seller_party_contains_vat_id_tax_number_and_address(): void {
        $invoice = $this->makeIssuedInvoice();
        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $party = '/ubl:Invoice/cac:AccountingSupplierParty/cac:Party';
        $this->assertSame('WorkDiary GmbH', $xp->evaluate("string($party/cac:PartyLegalEntity/cbc:RegistrationName)"));
        $this->assertSame('Musterstraße 1', $xp->evaluate("string($party/cac:PostalAddress/cbc:StreetName)"));
        $this->assertSame('12345', $xp->evaluate("string($party/cac:PostalAddress/cbc:PostalZone)"));
        $this->assertSame('DE', $xp->evaluate("string($party/cac:PostalAddress/cac:Country/cbc:IdentificationCode)"));
        // BT-31 (USt-IdNr., TaxScheme VAT) und BT-32 (Steuernummer, TaxScheme FC).
        $this->assertSame('DE123456789', $xp->evaluate("string($party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID='VAT']/cbc:CompanyID)"));
        $this->assertSame('12/345/67890', $xp->evaluate("string($party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID='FC']/cbc:CompanyID)"));
        $this->assertSame('rechnung@workdiary.example', $xp->evaluate("string($party/cac:Contact/cbc:ElectronicMail)"));
    }

    public function test_tax_subtotal_with_19_percent_standard_category(): void {
        $invoice = $this->makeIssuedInvoice();
        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $sub = '/ubl:Invoice/cac:TaxTotal/cac:TaxSubtotal';
        $this->assertSame('47.50', $xp->evaluate('string(/ubl:Invoice/cac:TaxTotal/cbc:TaxAmount)'));
        $this->assertSame('250.00', $xp->evaluate("string($sub/cbc:TaxableAmount)"));
        $this->assertSame('47.50', $xp->evaluate("string($sub/cbc:TaxAmount)"));
        $this->assertSame('S', $xp->evaluate("string($sub/cac:TaxCategory/cbc:ID)"));
        $this->assertSame('19.00', $xp->evaluate("string($sub/cac:TaxCategory/cbc:Percent)"));
        $this->assertSame('VAT', $xp->evaluate("string($sub/cac:TaxCategory/cac:TaxScheme/cbc:ID)"));
    }

    public function test_legal_monetary_total_is_consistent_with_invoice_fields(): void {
        $invoice = $this->makeIssuedInvoice();
        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $total = '/ubl:Invoice/cac:LegalMonetaryTotal';
        $this->assertSame('250.00', $xp->evaluate("string($total/cbc:LineExtensionAmount)"));
        $this->assertSame('250.00', $xp->evaluate("string($total/cbc:TaxExclusiveAmount)"));
        $this->assertSame('297.50', $xp->evaluate("string($total/cbc:TaxInclusiveAmount)"));
        $this->assertSame('297.50', $xp->evaluate("string($total/cbc:PayableAmount)"));
        $this->assertSame('EUR', $xp->evaluate("string($total/cbc:PayableAmount/@currencyID)"));
    }

    public function test_credit_note_uses_invoice_type_code_381(): void {
        $invoice = $this->makeIssuedInvoice('19.00', Invoice::TYPE_CREDIT_NOTE);
        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $this->assertSame('381', $xp->evaluate('string(/ubl:Invoice/cbc:InvoiceTypeCode)'));
    }

    public function test_unit_mapping_hours_to_hur_and_default_to_c62(): void {
        $invoice = $this->makeIssuedInvoice();
        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        // Position 1: "Std." ⇒ HUR; Position 2: "Pauschale" ⇒ Default C62.
        $this->assertSame('HUR', $xp->evaluate("string(/ubl:Invoice/cac:InvoiceLine[cbc:ID='1']/cbc:InvoicedQuantity/@unitCode)"));
        $this->assertSame('C62', $xp->evaluate("string(/ubl:Invoice/cac:InvoiceLine[cbc:ID='2']/cbc:InvoicedQuantity/@unitCode)"));
        $this->assertSame('200.00', $xp->evaluate("string(/ubl:Invoice/cac:InvoiceLine[cbc:ID='1']/cbc:LineExtensionAmount)"));
        $this->assertSame('100.00', $xp->evaluate("string(/ubl:Invoice/cac:InvoiceLine[cbc:ID='1']/cac:Price/cbc:PriceAmount)"));
        $this->assertSame('Beratung', $xp->evaluate("string(/ubl:Invoice/cac:InvoiceLine[cbc:ID='1']/cac:Item/cbc:Name)"));
    }

    public function test_zero_tax_rate_maps_to_category_z(): void {
        $invoice = $this->makeIssuedInvoice('0.00');
        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $this->assertSame('Z', $xp->evaluate('string(/ubl:Invoice/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:ID)'));
    }

    public function test_small_business_flag_maps_to_category_e_with_exemption_reason(): void {
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings(['small_business' => '1'])]]);
        $invoice = $this->makeIssuedInvoice('0.00');

        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $category = '/ubl:Invoice/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory';
        $this->assertSame('E', $xp->evaluate("string($category/cbc:ID)"));
        $this->assertNotSame('', $xp->evaluate("string($category/cbc:TaxExemptionReason)"));
        $this->assertSame('E', $xp->evaluate("string(/ubl:Invoice/cac:InvoiceLine[1]/cac:Item/cac:ClassifiedTaxCategory/cbc:ID)"));
    }

    public function test_payment_means_sepa_with_iban_and_payment_id(): void {
        $invoice = $this->makeIssuedInvoice();
        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $means = '/ubl:Invoice/cac:PaymentMeans';
        $this->assertSame('58', $xp->evaluate("string($means/cbc:PaymentMeansCode)"));
        $this->assertSame('R2026-0042', $xp->evaluate("string($means/cbc:PaymentID)"));
        $this->assertSame('DE89370400440532013000', $xp->evaluate("string($means/cac:PayeeFinancialAccount/cbc:ID)"));
        $this->assertSame('COBADEFFXXX', $xp->evaluate("string($means/cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID)"));
    }

    public function test_preflight_passes_for_complete_invoice(): void {
        $invoice = $this->makeIssuedInvoice();
        $result = app(XRechnungGenerator::class)->preflight($invoice);

        $this->assertSame([], $result['errors']);
    }

    public function test_preflight_flags_missing_buyer_reference(): void {
        $this->customer->update(['buyer_reference' => null]);
        $invoice = $this->makeIssuedInvoice();

        $result = app(XRechnungGenerator::class)->preflight($invoice);

        $this->assertContains((string) __('invoicing.einvoice.error.missing_buyer_reference'), $result['errors']);
    }

    public function test_preflight_flags_missing_seller_address_fields(): void {
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings(['street' => '', 'zip' => '', 'city' => ''])]]);
        $invoice = $this->makeIssuedInvoice();

        $result = app(XRechnungGenerator::class)->preflight($invoice);

        $this->assertCount(3, array_filter(
            $result['errors'],
            fn(string $e): bool => str_contains($e, (string) __('settings.einvoice.street'))
                || str_contains($e, (string) __('settings.einvoice.zip'))
                || str_contains($e, (string) __('settings.einvoice.city')),
        ));
    }

    public function test_preflight_flags_missing_vat_id_and_tax_number(): void {
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings(['vat_id' => '', 'tax_number' => ''])]]);
        $invoice = $this->makeIssuedInvoice();

        $result = app(XRechnungGenerator::class)->preflight($invoice);

        $this->assertContains((string) __('invoicing.einvoice.error.missing_tax_id'), $result['errors']);
    }

    public function test_preflight_flags_missing_iban(): void {
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings(['iban' => ''])]]);
        $invoice = $this->makeIssuedInvoice();

        $result = app(XRechnungGenerator::class)->preflight($invoice);

        $this->assertContains((string) __('invoicing.einvoice.error.missing_iban'), $result['errors']);
    }

    public function test_preflight_flags_draft_status(): void {
        $invoice = $this->makeIssuedInvoice();
        $invoice->update(['status' => Invoice::STATUS_DRAFT]);

        $result = app(XRechnungGenerator::class)->preflight($invoice->fresh(['items', 'customer']));

        $this->assertContains((string) __('invoicing.einvoice.error.status'), $result['errors']);
    }

    public function test_preflight_flags_totals_mismatch(): void {
        $invoice = $this->makeIssuedInvoice();
        // Summen manipulieren, ohne recalculate(): Positionssumme ≠ Zwischensumme.
        $invoice->forceFill(['subtotal' => '999.00'])->save();

        $result = app(XRechnungGenerator::class)->preflight($invoice->fresh(['items', 'customer']));

        $this->assertContains((string) __('invoicing.einvoice.error.totals_mismatch'), $result['errors']);
    }

    public function test_preflight_warns_on_incomplete_buyer_address_and_missing_bic(): void {
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings(['bic' => ''])]]);
        $this->customer->update(['address_street' => null]);
        $invoice = $this->makeIssuedInvoice();

        $result = app(XRechnungGenerator::class)->preflight($invoice);

        $this->assertSame([], $result['errors']);
        $this->assertContains((string) __('invoicing.einvoice.warning.missing_bic'), $result['warnings']);
        $this->assertContains((string) __('invoicing.einvoice.warning.buyer_address_incomplete'), $result['warnings']);
    }

    public function test_generate_throws_validation_exception_on_preflight_errors(): void {
        $this->customer->update(['buyer_reference' => null]);
        $invoice = $this->makeIssuedInvoice();

        $this->expectException(ValidationException::class);
        app(XRechnungGenerator::class)->generate($invoice);
    }

    public function test_seller_name_falls_back_to_organization_name(): void {
        $this->organization->update(['settings' => ['einvoice' => $this->sellerSettings(['seller_name' => ''])]]);
        $invoice = $this->makeIssuedInvoice();

        $xml = app(XRechnungGenerator::class)->generate($invoice);
        $xp = $this->xpath($xml);

        $this->assertSame('WorkDiary Org', $xp->evaluate('string(/ubl:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName)'));
    }
}
