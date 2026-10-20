<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceRevalidationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Document\DocumentService;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Neuvalidierung im Rechnungseingang (MVP-1248): Neun UBL-Eingänge trugen
 * „KoSIT-Validator nicht verfügbar“ aus dem Toolkit-Stand beim Empfang, obwohl
 * der Validator läuft. Sammelaktion und Befehl wiederholen nur die Prüfung am
 * abgelegten Original; Werte, Zuordnung und Übergabe bleiben.
 */
class IncomingInvoiceRevalidationTest extends TestCase {
    use RefreshDatabase, WithOrganization;

    private const UBL = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
          <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0</cbc:CustomizationID>
          <cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>
          <cbc:ID>SONDE-1</cbc:ID>
          <cbc:IssueDate>2026-05-18</cbc:IssueDate>
          <cbc:DueDate>2026-06-01</cbc:DueDate>
          <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
          <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
          <cbc:BuyerReference>Sonde</cbc:BuyerReference>
          <cac:AccountingSupplierParty><cac:Party>
            <cac:PartyName><cbc:Name>Sonde Verkäufer GmbH</cbc:Name></cac:PartyName>
            <cac:PostalAddress><cbc:StreetName>Weg 1</cbc:StreetName><cbc:CityName>Berlin</cbc:CityName><cbc:PostalZone>10115</cbc:PostalZone><cac:Country><cbc:IdentificationCode>DE</cbc:IdentificationCode></cac:Country></cac:PostalAddress>
            <cac:PartyTaxScheme><cbc:CompanyID>DE987654321</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>
            <cac:PartyLegalEntity><cbc:RegistrationName>Sonde Verkäufer GmbH</cbc:RegistrationName></cac:PartyLegalEntity>
          </cac:Party></cac:AccountingSupplierParty>
          <cac:AccountingCustomerParty><cac:Party>
            <cac:PartyName><cbc:Name>Sonde Käufer</cbc:Name></cac:PartyName>
            <cac:PostalAddress><cbc:StreetName>Straße 2</cbc:StreetName><cbc:CityName>Potsdam</cbc:CityName><cbc:PostalZone>14469</cbc:PostalZone><cac:Country><cbc:IdentificationCode>DE</cbc:IdentificationCode></cac:Country></cac:PostalAddress>
            <cac:PartyLegalEntity><cbc:RegistrationName>Sonde Käufer</cbc:RegistrationName></cac:PartyLegalEntity>
          </cac:Party></cac:AccountingCustomerParty>
          <cac:TaxTotal>
            <cbc:TaxAmount currencyID="EUR">19.00</cbc:TaxAmount>
            <cac:TaxSubtotal><cbc:TaxableAmount currencyID="EUR">100.00</cbc:TaxableAmount><cbc:TaxAmount currencyID="EUR">19.00</cbc:TaxAmount><cac:TaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>19</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory></cac:TaxSubtotal>
          </cac:TaxTotal>
          <cac:LegalMonetaryTotal>
            <cbc:LineExtensionAmount currencyID="EUR">100.00</cbc:LineExtensionAmount>
            <cbc:TaxExclusiveAmount currencyID="EUR">100.00</cbc:TaxExclusiveAmount>
            <cbc:TaxInclusiveAmount currencyID="EUR">119.00</cbc:TaxInclusiveAmount>
            <cbc:PayableAmount currencyID="EUR">119.00</cbc:PayableAmount>
          </cac:LegalMonetaryTotal>
          <cac:InvoiceLine>
            <cbc:ID>1</cbc:ID>
            <cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID="EUR">100.00</cbc:LineExtensionAmount>
            <cac:Item><cbc:Name>Sondenposition</cbc:Name><cac:ClassifiedTaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>19</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory></cac:Item>
            <cac:Price><cbc:PriceAmount currencyID="EUR">100.00</cbc:PriceAmount></cac:Price>
          </cac:InvoiceLine>
        </Invoice>
        XML;

    /** Validierung, wie sie der Empfang vor dem 18.10.2026 ohne Validator schrieb. */
    private const STALE = ['schema_checked' => true, 'schema_errors' => [], 'kosit_available' => false, 'kosit_valid' => null, 'kosit_errors' => []];

    private const JUDGED = ['schema_checked' => true, 'schema_errors' => [], 'kosit_applicable' => true, 'kosit_available' => true, 'kosit_valid' => true, 'kosit_scenario' => 'EN16931 XRechnung (UBL Invoice)', 'kosit_errors' => []];

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE123456789']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        // Kein JVM-Start je Test: der KoSIT-Validator gilt als nicht installiert.
        putenv('KOSIT_VALIDATOR_JAR=/nonexistent/validator.jar');
    }

    protected function tearDown(): void {
        putenv('KOSIT_VALIDATOR_JAR');
        parent::tearDown();
    }

    public function test_revalidation_rewrites_the_verdict_and_leaves_the_rest_alone(): void {
        $incoming = $this->structuredIncoming(self::STALE);
        $this->assertTrue(IncomingEInvoiceService::lacksKositVerdict($incoming));

        $this->assertTrue(app(IncomingEInvoiceService::class)->revalidate($incoming));

        $incoming->refresh();
        $validation = (array) data_get($incoming->summary, 'validation');
        $this->assertTrue($validation['schema_checked']);
        $this->assertArrayHasKey('kosit_applicable', $validation);
        $this->assertArrayHasKey('kosit_scenario', $validation);
        $this->assertSame('SONDE-1', $incoming->invoice_number);
        $this->assertSame('SONDE-1', data_get($incoming->summary, 'number'));
        $this->assertSame('Sonde Verkäufer GmbH', data_get($incoming->summary, 'seller'));
        $this->assertTrue($incoming->auditLogs()->where('event', 'incoming_einvoice.revalidated')->exists());
    }

    public function test_rejected_and_unstructured_entries_are_skipped(): void {
        $rejected = $this->structuredIncoming(self::STALE, ['status' => IncomingEInvoiceStatus::Rejected]);
        $extracted = $this->structuredIncoming(null, ['recognition' => IncomingInvoiceRecognition::Extracted]);

        foreach ([$rejected, $extracted] as $incoming) {
            $this->assertFalse(IncomingEInvoiceService::isRevalidatable($incoming));
            $this->assertFalse(app(IncomingEInvoiceService::class)->revalidate($incoming));
            $this->assertFalse($incoming->auditLogs()->where('event', 'incoming_einvoice.revalidated')->exists());
        }
    }

    public function test_bulk_action_reports_checked_and_skipped(): void {
        $stale = $this->structuredIncoming(self::STALE);
        $rejected = $this->structuredIncoming(self::STALE, ['status' => IncomingEInvoiceStatus::Rejected]);

        $this->post(route('finance.incoming-invoices.bulk-revalidate'), ['ids' => [$stale->sqid, $rejected->sqid]])
            ->assertRedirect()
            ->assertSessionHas('warning', __(':checked Eingänge neu geprüft, :skipped übersprungen.', ['checked' => 1, 'skipped' => 1]));

        $this->assertTrue($stale->auditLogs()->where('event', 'incoming_einvoice.revalidated')->exists());
        $this->assertFalse($rejected->auditLogs()->where('event', 'incoming_einvoice.revalidated')->exists());
    }

    public function test_the_button_sits_in_the_bulk_toolbar_without_the_party_requirement(): void {
        $this->structuredIncoming(self::STALE);

        $html = $this->get(route('finance.incoming-invoices.index', ['tab' => 'all']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*formaction="[^"]*sammel-pruefen"[^>]*formnovalidate/', $html);
    }

    public function test_command_takes_only_entries_without_a_kosit_verdict_unless_all(): void {
        $stale = $this->structuredIncoming(self::STALE);
        $judged = $this->structuredIncoming(self::JUDGED);
        $this->assertFalse(IncomingEInvoiceService::lacksKositVerdict($judged));

        $this->artisan('incoming-invoices:revalidate', ['--organization' => $this->organization->id])
            ->expectsOutputToContain('1 neu geprüft, 1 mit vorhandenem Urteil übersprungen, 0 ohne lesbares Original')
            ->assertSuccessful();
        $this->assertTrue($stale->auditLogs()->where('event', 'incoming_einvoice.revalidated')->exists());
        $this->assertFalse($judged->auditLogs()->where('event', 'incoming_einvoice.revalidated')->exists());

        $this->artisan('incoming-invoices:revalidate', ['--organization' => $this->organization->id, '--all' => true])
            ->expectsOutputToContain('2 neu geprüft, 0 mit vorhandenem Urteil übersprungen')
            ->assertSuccessful();
        $this->assertTrue($judged->auditLogs()->where('event', 'incoming_einvoice.revalidated')->exists());
    }

    /**
     * Strukturierter Eingang wie aus dem Postfach: Dokument mit abgelegter UBL, Summary mit der gegebenen Validierung.
     *
     * @param  array<string, mixed>|null  $validation
     * @param  array<string, mixed>  $attributes
     */
    private function structuredIncoming(?array $validation, array $attributes = []): IncomingEInvoice {
        $document = Document::factory()->create([
            'organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice,
            'created_by_user_id' => $this->admin->id, 'title' => 'E-Rechnung SONDE-1 — Sonde Verkäufer GmbH',
        ]);
        app(DocumentService::class)->addVersionFromContents($document, $this->admin, self::UBL, 'rechnung.xml', 'application/xml');

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'sender_email' => 'info@example.test', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::Structured, 'invoice_number' => 'SONDE-1', 'seller_name' => 'Sonde Verkäufer GmbH',
            'summary' => ['number' => 'SONDE-1', 'seller' => 'Sonde Verkäufer GmbH', 'gross' => '119.00', 'recognition' => IncomingInvoiceRecognition::Structured->value, 'validation' => $validation],
            ...$attributes,
        ]);
    }
}
