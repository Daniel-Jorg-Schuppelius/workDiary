<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceGaebExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Models\{Customer, Invoice};
use App\Services\Gaeb\GaebInvoiceExportService;
use ERechnungToolkit\Enums\GaebPhase;
use ERechnungToolkit\Validators\GaebSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rechnungen als GAEB-Datei (X89) — Ausgabeformat, kein zweiter Rechnungskreis
 * (D8). Was feststeht, wird geschrieben; nachgerechnet wird nichts.
 */
final class InvoiceGaebExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->organization->update(['settings' => array_replace((array) $this->organization->settings, [
            'einvoice' => [
                'seller_name' => 'Bau GmbH',
                'street' => 'Schillerplatz 1',
                'zip' => '99423',
                'city' => 'Weimar',
                'tax_number' => '205/5711/0041',
            ],
        ])]);

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Stadt Bonn',
            'address_street' => 'Berliner Platz 2',
            'address_zip' => '53111',
            'address_city' => 'Bonn',
        ]);
    }

    private function invoice(string $type = Invoice::TYPE_PARTIAL, string $number = 'RE-2026-0042'): Invoice {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => Invoice::STATUS_ISSUED,
            'type' => $type,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'issued_on' => '2026-08-17',
        ]);

        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Erdarbeiten',
            'quantity' => '10',
            'unit' => 'm3',
            'unit_price' => '100.00',
            'position' => 1,
            'service_date' => '2026-07-15',
        ]);

        // Die Beträge rechnet die Faktura, nicht der Export - er schreibt nur,
        // was feststeht.
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer', 'organization']);
    }

    /** Die Rechnung wird schemavalide geschrieben und trägt ihre Anteile. */
    public function test_export_produces_a_valid_invoice(): void {
        $result = app(GaebInvoiceExportService::class)->export($this->invoice());

        $this->assertSame([], (new GaebSchemaValidator)->validate($result['content']));
        $this->assertStringContainsString('<Invoice>', $result['content']);
        $this->assertStringContainsString('<InvoiceNo>RE-2026-0042</InvoiceNo>', $result['content']);
        $this->assertStringContainsString('<TaxNo>205/5711/0041</TaxNo>', $result['content']);
        // Grundbetrag und Umsatzsteuer — die beiden, die jede Rechnung hat.
        $this->assertStringContainsString('<InvoiceShareType>basic amount</InvoiceShareType>', $result['content']);
        $this->assertStringContainsString('<InvoiceShareType>VAT</InvoiceShareType>', $result['content']);
        $this->assertStringContainsString('<TotalGross>1190</TotalGross>', $result['content']);
    }

    /**
     * Die Rechnungsart entscheidet, was die Beträge bedeuten: Ein Abschlag wird
     * später verrechnet, eine Schlussrechnung schließt den Auftrag.
     */
    public function test_invoice_type_follows_the_kind_of_invoice(): void {
        $export = app(GaebInvoiceExportService::class);

        $this->assertStringContainsString(
            '<InvoiceType>deduction</InvoiceType>',
            $export->export($this->invoice(Invoice::TYPE_PARTIAL))['content']
        );
        $this->assertStringContainsString(
            '<InvoiceType>final account</InvoiceType>',
            $export->export($this->invoice(Invoice::TYPE_FINAL, 'RE-2026-0043'))['content']
        );
    }

    /**
     * Der Leistungszeitraum steht nicht an der Rechnung, sondern ergibt sich
     * aus den Positionen.
     */
    public function test_service_period_comes_from_the_items(): void {
        $result = app(GaebInvoiceExportService::class)->export($this->invoice());

        $this->assertStringContainsString('<ServiceProvisionStartDate>2026-07-15</ServiceProvisionStartDate>', $result['content']);
    }

    /**
     * Die rechnungsbegründende Unterlage ist keine Rechnung: Sie verweist auf
     * deren Nummer, statt eine eigene zu führen.
     */
    public function test_attachment_refers_to_the_invoice(): void {
        $result = app(GaebInvoiceExportService::class)->export($this->invoice(), GaebPhase::InvoiceAttachment);

        $this->assertSame([], (new GaebSchemaValidator)->validate($result['content']));
        $this->assertStringContainsString('<RefInvoiceNo>RE-2026-0042</RefInvoiceNo>', $result['content']);
        $this->assertStringNotContainsString('<InvoiceNo>', $result['content']);
    }

    /** Fehlt die Steuernummer, wird das benannt — Pflichtangabe des Steuerrechts. */
    public function test_missing_tax_number_is_reported(): void {
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Bau GmbH', 'street' => 'Schillerplatz 1', 'zip' => '99423', 'city' => 'Weimar',
        ]]]);

        $result = app(GaebInvoiceExportService::class)->export($this->invoice());

        $this->assertNotSame([], $result['losses']);
        $this->assertStringContainsString('Steuernummer', implode(' ', $result['losses']));
    }
}
