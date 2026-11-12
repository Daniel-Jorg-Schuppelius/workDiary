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
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use ERechnungToolkit\Parsers\ERechnungParser;
use ERechnungToolkit\Validators\{KositValidator, ValidationMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623: Befreiungsgrund je Steueraufriss (BG-23) für die Kategorien ohne
 * Steuer — innergemeinschaftliche Lieferung (K), Ausfuhr (G), nicht steuerbar
 * (O), steuerfrei (E) nach § 19 und nach § 4 UStG. Geprüft über den
 * KoSIT-Validator des php-erechnung-toolkit.
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

    public function test_export_to_a_third_country_carries_the_export_reason(): void {
        $invoice = $this->issued($this->customer('CH'), [['Wartung Steuerungsanlage', null, null]]);

        $xml = app(XRechnungGenerator::class)->generate($invoice);

        $subtotal = $this->subtotal($xml, 'G');
        $this->assertStringContainsString('VATEX-EU-G', $xml);
        $this->assertSame((string) __('invoicing.einvoice.exemption_export_services'), $subtotal->getExemptionReason());
        $this->assertKositAccepted($xml);
    }

    public function test_intra_community_supply_carries_the_ic_reason_and_buyer_vat_id(): void {
        $customer = $this->customer('AT', 'ATU12345675');
        $invoice = $this->issued($customer, [['Steuermodul S7', 'K', '0.00']]);
        $generator = app(XRechnungGenerator::class);

        $xml = $generator->generate($invoice);

        $subtotal = $this->subtotal($xml, 'K');
        $this->assertStringContainsString('VATEX-EU-IC', $xml);
        $this->assertSame((string) __('invoicing.einvoice.exemption_intra_community'), $subtotal->getExemptionReason());
        $this->assertContains((string) __('invoicing.einvoice.warning.intra_community_delivery_country'), $generator->preflight($invoice)['warnings']);
        // BR-IC-12 (Lieferland BT-80) gibt das erechnung-toolkit noch nicht aus — Toolkit-Kandidat; alles andere muss passen.
        // Behebt ein Toolkit-Release die Lücke, schlägt die zweite Prüfung fehl: dann Warnung und Ausnahme entfernen.
        $this->assertSame(['BR-IC-12'], $this->assertKositAccepted($xml, ['BR-IC-12']));

        // Die ausgestellte Rechnung behält die eingefrorene USt-IdNr. (MVP-1624); fehlt sie beim Ausstellen, hält die Prüfung an.
        $customer->update(['vat_id' => null]);
        $this->assertNotContains((string) __('invoicing.einvoice.error.intra_community_buyer_vat_id'), $generator->preflight($invoice->refresh())['errors']);
        $withoutVatId = $this->issued($customer->refresh(), [['Steuermodul S7', 'K', '0.00']]);
        $this->assertContains((string) __('invoicing.einvoice.error.intra_community_buyer_vat_id'), $generator->preflight($withoutVatId)['errors']);
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
        $this->assertContains((string) __('invoicing.einvoice.warning.not_subject_line_rate'), app(XRechnungGenerator::class)->preflight($invoice)['warnings']);
        // BR-O-05: Das erechnung-toolkit schreibt den Positionssatz (BT-152) auch bei O — Toolkit-Kandidat.
        $this->assertSame(['BR-O-05'], $this->assertKositAccepted($xml, ['BR-O-05']));
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
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Entwurf über den TaxResolver, ausgestellt.
     *
     * @param list<array{0: string, 1: ?string, 2: ?string}> $lines Beschreibung, Steuerkategorie, Steuersatz
     */
    private function issued(Customer $customer, array $lines): Invoice {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($customer);
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

    /**
     * @param list<string> $knownGaps Regeln, die eine benannte Toolkit-Lücke verletzt
     * @return list<string> davon tatsächlich gemeldete Regeln
     */
    private function assertKositAccepted(string $xml, array $knownGaps = []): array {
        $kosit = new KositValidator;
        if (! $kosit->isAvailable()) {
            $this->markTestSkipped('KoSIT-Validator (Java + JAR) ist nicht verfügbar.');
        }
        $errors = $kosit->validate($xml)->getErrors();
        $unexpected = array_filter($errors, static fn (ValidationMessage $message): bool => ! in_array($message->getCode(), $knownGaps, true));
        $this->assertSame([], array_map('strval', array_values($unexpected)));

        return array_values(array_unique(array_map(static fn (ValidationMessage $message): string => $message->getCode(), array_diff_key($errors, $unexpected))));
    }
}
