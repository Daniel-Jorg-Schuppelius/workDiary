<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceCompanionPairingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Invoicing\IncomingInvoiceRecognition;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingOrigin};
use App\Services\Mail\MailAttachment;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Befund 2026-10-20 (MVP-1247): Ein niederländischer Shop schickt UBL ohne
 * AccountingSupplierParty, mit „Name, Bestellnummer, USt-IdNr.“ als cbc:ID und
 * die Sichtrechnung als PDF. Beide meinen dieselbe Rechnung, obwohl die
 * Nummernschlüssel ungleich sind; die PDF ergänzt, was die XML schuldig bleibt.
 */
class IncomingInvoiceCompanionPairingTest extends TestCase {
    use RefreshDatabase, WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE123456789']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        // Kein JVM-Start je Test: der KoSIT-Validator gilt als nicht installiert.
        putenv('KOSIT_VALIDATOR_JAR=/nonexistent/validator.jar');
    }

    protected function tearDown(): void {
        putenv('KOSIT_VALIDATOR_JAR');
        parent::tearDown();
    }

    public function test_ubl_without_seller_pairs_with_its_pdf_and_takes_the_sellers_vat_id(): void {
        $result = $this->store([$this->shopPdf(), $this->shopUbl()]);

        $this->assertSame(1, $result['stored']);
        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(IncomingInvoiceRecognition::Structured, $incoming->recognition);
        $this->assertSame('Muster Käufer, 7000001, DE123456789', $incoming->invoice_number);
        $this->assertSame('NL123456782B01', $incoming->seller_vat_id);
        $this->assertSame('ABNANL2AXXX', $incoming->creditor_bic);
        $this->assertSame('258.60', $incoming->amount_gross?->getAmount());
        $this->assertSame(['invoice_7000001.pdf'], $incoming->attachments->pluck('original_name')->all());
        $this->assertSame(['seller_vat', 'creditor_bic'], data_get($incoming->summary, 'companion_fill.fields'));
        $this->assertSame('invoice_7000001.pdf', data_get($incoming->summary, 'companion_fill.file'));
        $this->assertContains(
            __('Aus der Begleitdatei :file ergänzt: :fields', ['file' => 'invoice_7000001.pdf', 'fields' => 'USt-IdNr., BIC']),
            (array) data_get($incoming->summary, 'notices'),
        );
        // Der Schemafehler des Absenders bleibt sichtbar.
        $this->assertNotEmpty(data_get($incoming->summary, 'validation.schema_errors'));

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))
            ->assertOk()->assertSee('invoice_7000001.pdf')->assertSee('USt-IdNr., BIC');
    }

    public function test_attachment_order_does_not_matter(): void {
        $this->store([$this->shopUbl(), $this->shopPdf()]);

        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame('NL123456782B01', $incoming->seller_vat_id);
        $this->assertSame(['invoice_7000001.pdf'], $incoming->attachments->pluck('original_name')->all());
    }

    public function test_a_known_supplier_is_matched_through_the_companions_vat_id(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fietsenhuis-Voorbeeld.com', 'company' => 'Fietsenhuis-Voorbeeld.com', 'vat_id' => 'NL123456782B01']);

        $this->store([$this->shopPdf(), $this->shopUbl()]);

        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame($supplier->id, $incoming->supplier_id);
        $this->assertStringContainsString('Fietsenhuis-Voorbeeld.com', (string) $incoming->seller_name);
        $this->assertContains('seller', (array) data_get($incoming->summary, 'companion_fill.fields'));
    }

    public function test_unrelated_numbers_still_become_two_incomings(): void {
        $this->store([$this->shopPdf(), $this->shopUbl('RE-77')]);

        $this->assertSame(2, IncomingEInvoice::query()->count());
        $this->assertSame(0, IncomingEInvoice::query()->whereHas('attachments')->count());
    }

    /**
     * @param  list<MailAttachment>  $attachments
     * @return array<string, mixed>
     */
    private function store(array $attachments): array {
        return app(IncomingEInvoiceService::class)->storeMessage(
            $this->admin,
            $attachments,
            new IncomingOrigin('shop@example.test', '<order-7000001@example.test>', 'Rechnung 7000001'),
        );
    }

    /** Sichtrechnung des Shops: Nummer, Beträge, eigene und fremde USt-IdNr., IBAN. */
    private function shopPdf(): MailAttachment {
        $dompdf = new Dompdf;
        $dompdf->loadHtml(<<<'HTML'
            <!doctype html><html lang="de"><body>
            <p>Verkauft an: Muster Käufer, Musterweg 1, 12345 Berlin, Germany</p>
            <p>Bestellnummer/ Rechnungsnummer: 7000001 Bestelldatum: 18-05-2026 BTW-nr: DE123456789</p>
            <table>
                <tr><th>Artikel</th><th>Stückzahl</th><th>Preis (inkl.)</th><th>MwSt.</th><th>Total (inkl.)</th></tr>
                <tr><td>Nabe Interface</td><td>1</td><td>€ 233,02</td><td>0.00%</td><td>€ 233,02</td></tr>
                <tr><td>Freilauf Set</td><td>1</td><td>€ 25,58</td><td>0.00%</td><td>€ 25,58</td></tr>
            </table>
            <p>Zwischensumme: € 258,60</p>
            <p>Zahlungsart: PayPal</p>
            <p>Total: € 258,60</p>
            <p>Fietsenhuis-Voorbeeld.com, Voorbeeldweg 1, 1234AB Voorbeeldstad</p>
            <p>Btw-nr. : NL123456782B01 KVK nr. : 12345678</p>
            <p>IBAN nr: NL53ABNA0542298961</p>
            <p>Swift nr: ABNANL2A</p>
            </body></html>
            HTML);
        $dompdf->render();

        return new MailAttachment('invoice_7000001.pdf', 'application/pdf', (string) $dompdf->output());
    }

    /** UBL des Shops: kein AccountingSupplierParty, Zahlungsdienst als Käufer, cbc:ID als Freitext, BIC verschachtelt. */
    private function shopUbl(string $id = 'Muster Käufer, 7000001, DE123456789'): MailAttachment {
        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
                <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
                <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:nen.nl:nlcius:v1.0</cbc:CustomizationID>
                <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
                <cbc:ID>{$id}</cbc:ID>
                <cbc:IssueDate>2026-05-18</cbc:IssueDate>
                <cbc:InvoiceTypeCode listID="UNCL1001" listAgencyID="6">380</cbc:InvoiceTypeCode>
                <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
                <cac:AccountingCustomerParty>
                    <cbc:SupplierAssignedAccountID>PayPal Europe (zakelijk binnen EEG)</cbc:SupplierAssignedAccountID>
                    <cac:Party><cac:PartyName><cbc:Name>PayPal Europe (zakelijk binnen EEG)</cbc:Name></cac:PartyName></cac:Party>
                </cac:AccountingCustomerParty>
                <cac:PaymentMeans>
                    <cbc:PaymentMeansCode listID="UNCL4461">1</cbc:PaymentMeansCode>
                    <cbc:PaymentDueDate>2026-05-18</cbc:PaymentDueDate>
                    <cac:PayeeFinancialAccount>
                        <cbc:ID schemeID="IBAN">NL53ABNA0542298961</cbc:ID>
                        <cac:FinancialInstitutionBranch><cac:FinancialInstitution><cbc:ID schemeID="BIC">ABNANL2A</cbc:ID></cac:FinancialInstitution></cac:FinancialInstitutionBranch>
                    </cac:PayeeFinancialAccount>
                </cac:PaymentMeans>
                <cac:TaxTotal>
                    <cbc:TaxAmount currencyID="EUR">0</cbc:TaxAmount>
                    <cac:TaxSubtotal>
                        <cbc:TaxableAmount currencyID="EUR">258.6</cbc:TaxableAmount>
                        <cbc:TaxAmount currencyID="EUR">0</cbc:TaxAmount>
                        <cac:TaxCategory><cbc:ID schemeID="UNCL5305">S</cbc:ID><cbc:Percent>21</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory>
                    </cac:TaxSubtotal>
                </cac:TaxTotal>
                <cac:LegalMonetaryTotal>
                    <cbc:LineExtensionAmount currencyID="EUR">258.6</cbc:LineExtensionAmount>
                    <cbc:TaxExclusiveAmount currencyID="EUR">258.6</cbc:TaxExclusiveAmount>
                    <cbc:TaxInclusiveAmount currencyID="EUR">258.6</cbc:TaxInclusiveAmount>
                    <cbc:PayableAmount currencyID="EUR">258.6</cbc:PayableAmount>
                </cac:LegalMonetaryTotal>
                <cac:InvoiceLine>
                    <cbc:ID>7000001</cbc:ID>
                    <cbc:InvoicedQuantity unitCode="ZZ">1</cbc:InvoicedQuantity>
                    <cbc:LineExtensionAmount currencyID="EUR">258.6</cbc:LineExtensionAmount>
                    <cac:TaxTotal><cbc:TaxAmount currencyID="EUR">0</cbc:TaxAmount></cac:TaxTotal>
                    <cac:Item>
                        <cbc:Name>{$id}</cbc:Name>
                        <cac:ClassifiedTaxCategory><cbc:ID schemeID="UNCL5305">S</cbc:ID><cbc:Percent>21.00</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory>
                    </cac:Item>
                    <cac:Price><cbc:PriceAmount currencyID="EUR">258.6</cbc:PriceAmount><cbc:BaseQuantity>1</cbc:BaseQuantity></cac:Price>
                </cac:InvoiceLine>
            </Invoice>
            XML;

        return new MailAttachment('ubl_invoice_7000001.xml', 'text/xml', $xml);
    }
}
