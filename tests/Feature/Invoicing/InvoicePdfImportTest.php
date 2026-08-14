<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceDeliveryFormat;
use App\Models\{Customer, Document, Invoice, Organization, User};
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

final class InvoicePdfImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Beispielkunde GmbH',
            'currency' => 'EUR',
            'buyer_reference' => 'KUNDEN-REF',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_pdf_is_imported_as_editable_einvoice_draft_with_original_document(): void {
        $response = $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Zugferd->value,
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $this->samplePdf()),
        ]);

        $invoice = Invoice::query()->firstOrFail();
        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame('PDF-2026-17', $invoice->number);
        $this->assertSame('file_import', $invoice->number_source);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame('2026-08-14', $invoice->issued_on?->toDateString());
        $this->assertSame('2026-08-28', $invoice->due_on?->toDateString());
        $this->assertSame(InvoiceDeliveryFormat::Zugferd, $invoice->delivery_format);
        $this->assertSame('991-12345-67', $invoice->buyer_reference);
        $this->assertSame('1000.00', $invoice->subtotal?->getAmount());
        $this->assertSame('1190.00', $invoice->total?->getAmount());
        $this->assertSame(1, $invoice->items()->count());

        // Zahlungsdaten aus dem Text: Skonto, Zahlungsziel, IBAN (Gegenprobe).
        $this->assertSame('2.00', $invoice->skonto_percent?->getNumericValue());
        $this->assertSame(7, $invoice->skonto_days);
        $this->assertSame(14, $invoice->payment_terms_days);
        $this->assertSame('DE89370400440532013000', data_get($invoice->import_metadata, 'extraction.payment.iban'));

        $document = Document::query()->firstOrFail();
        $this->assertSame($invoice->getMorphClass(), $document->documentable_type);
        $this->assertSame($invoice->id, $document->documentable_id);
        $this->assertTrue($document->confidential);
        Storage::disk('local')->assertExists((string) $document->currentVersion?->path);

        $this->actingAs($this->admin)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('invoice-import.imported_notice'))
            ->assertSee(InvoiceDeliveryFormat::Zugferd->label());
    }

    public function test_draft_einvoice_options_are_editable_and_invoice_reference_overrides_customer(): void {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-0010',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->patch(route('invoices.einvoice-options.update', $invoice), [
            'number' => 'FREMD-2026-10',
            'issued_on' => '2026-08-01',
            'due_on' => '2026-08-15',
            'currency' => 'EUR',
            'buyer_reference' => 'RECHNUNGS-REF',
            'delivery_format' => InvoiceDeliveryFormat::PdfAndXRechnung->value,
        ])->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('FREMD-2026-10', $invoice->number);
        $this->assertSame('RECHNUNGS-REF', $invoice->buyer_reference);
        $this->assertSame(InvoiceDeliveryFormat::PdfAndXRechnung, $invoice->delivery_format);
    }

    public function test_docx_invoice_uses_the_same_import_pipeline(): void {
        $path = tempnam(sys_get_temp_dir(), 'invoice-docx-') . '.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', <<<'XML'
            <?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
            <w:p><w:r><w:t>Rechnungsnummer: WORD-2026-1</w:t></w:r></w:p>
            <w:p><w:r><w:t>Rechnungsdatum: 14.08.2026</w:t></w:r></w:p>
            <w:p><w:r><w:t>Nettobetrag 100,00 EUR</w:t></w:r></w:p>
            <w:p><w:r><w:t>Umsatzsteuer 19 % 19,00 EUR</w:t></w:r></w:p>
            <w:p><w:r><w:t>Gesamtbetrag 119,00 EUR</w:t></w:r></w:p>
            </w:body></w:document>
            XML);
        $zip->close();

        $file = new UploadedFile($path, 'rechnung.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::XRechnung->value,
            'file' => $file,
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('WORD-2026-1', $invoice->number);
        $this->assertSame('docx', data_get($invoice->import_metadata, 'source'));
    }

    public function test_xlsx_invoice_uses_the_same_import_pipeline(): void {
        $path = tempnam(sys_get_temp_dir(), 'invoice-xlsx-') . '.xlsx';
        $builder = (new \CommonToolkit\Builders\XLSXDocumentBuilder)->sheet('Rechnung')->addRows([
            ['Rechnungsnummer:', 'EXCEL-2026-1'],
            ['Rechnungsdatum:', '14.08.2026'],
            ['Nettobetrag', 100.00, 'EUR'],
            ['Umsatzsteuer 19 %', 19.00, 'EUR'],
            ['Gesamtbetrag', 119.00, 'EUR'],
        ]);
        \CommonToolkit\Generators\XLSX\XLSXGenerator::toFile($builder->build(), $path);

        $file = new UploadedFile($path, 'rechnung.xlsx', \App\Support\XlsxExport::MIME, null, true);
        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Zugferd->value,
            'file' => $file,
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('EXCEL-2026-1', $invoice->number);
        $this->assertSame('xlsx', data_get($invoice->import_metadata, 'source'));
    }

    public function test_customer_default_delivery_format_is_used_when_none_selected(): void {
        $this->customer->update(['delivery_format' => InvoiceDeliveryFormat::PdfAndXRechnung->value]);

        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $this->samplePdf()),
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame(InvoiceDeliveryFormat::PdfAndXRechnung, $invoice->delivery_format);
    }

    public function test_import_review_can_be_confirmed_and_original_previewed_inline(): void {
        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Zugferd->value,
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $this->samplePdf()),
        ])->assertRedirect();
        $invoice = Invoice::query()->firstOrFail();

        $this->actingAs($this->admin)->get(route('invoices.import-review', $invoice))
            ->assertOk()
            ->assertSee(__('invoice-import.review_detected'))
            ->assertSee('PDF-2026-17');

        $this->actingAs($this->admin)->get(route('invoices.pdf-import.preview', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin)->post(route('invoices.import-review.confirm', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));
        $this->assertTrue((bool) data_get($invoice->fresh()?->import_metadata, 'reviewed'));
    }

    public function test_xlsx_item_table_is_detected_and_creates_real_line_items(): void {
        $path = tempnam(sys_get_temp_dir(), 'invoice-xlsx-') . '.xlsx';
        $builder = (new \CommonToolkit\Builders\XLSXDocumentBuilder)->sheet('Rechnung')->addRows([
            ['Rechnungsnummer:', 'EXCEL-2026-7'],
            ['Rechnungsdatum:', '14.08.2026'],
            ['', ''],
            ['Pos', 'Bezeichnung', 'Menge', 'Einheit', 'Einzelpreis', 'Betrag'],
            [1, 'Beratung', 2.0, 'Std.', 90.00, 180.00],
            [2, 'Anfahrt', 1.0, 'pauschal', 20.00, 20.00],
            ['', 'Summe netto', '', '', '', 200.00],
            ['', 'Umsatzsteuer 19 %', '', '', '', 38.00],
            ['', 'Gesamtbetrag', '', '', '', 238.00],
        ]);
        \CommonToolkit\Generators\XLSX\XLSXGenerator::toFile($builder->build(), $path);

        $file = new UploadedFile($path, 'rechnung.xlsx', \App\Support\XlsxExport::MIME, null, true);
        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Zugferd->value,
            'file' => $file,
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('EXCEL-2026-7', $invoice->number);
        $items = $invoice->items()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame('Beratung', $items[0]->description);
        $this->assertSame('2.000', $items[0]->quantity);
        $this->assertSame('Std.', $items[0]->unit);
        $this->assertSame('90.0000', $items[0]->unit_price?->getAmount());
        $this->assertSame('Anfahrt', $items[1]->description);
        $this->assertSame('200.00', $invoice->subtotal?->getAmount());
        $this->assertSame('238.00', $invoice->total?->getAmount());
    }

    public function test_xrechnung_xml_upload_creates_structured_draft_with_real_line_items(): void {
        $xml = $this->sampleXRechnungXml();

        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::PdfAndXRechnung->value,
            'file' => UploadedFile::fake()->createWithContent('xrechnung.xml', $xml),
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('ER-2026-0042', $invoice->number);
        $this->assertSame('file_import', $invoice->number_source);
        $this->assertSame('2026-06-01', $invoice->issued_on?->toDateString());
        $this->assertSame('2026-06-15', $invoice->due_on?->toDateString());
        $this->assertSame('991-12345-67', $invoice->buyer_reference);
        $this->assertTrue((bool) data_get($invoice->import_metadata, 'extraction.structured'));
        $this->assertSame('xrechnung_xml', data_get($invoice->import_metadata, 'extraction.source_format'));

        // Echte Positionen statt Sammelzeile — inkl. Einheit und Positionsrabatt.
        $items = $invoice->items()->orderBy('position')->get();
        $this->assertCount(2, $items);
        $this->assertSame('Wartungspauschale', $items[0]->description);
        $this->assertSame('2.000', $items[0]->quantity);
        $this->assertSame('Std.', $items[0]->unit);
        $this->assertSame('100.0000', $items[0]->unit_price?->getAmount());
        $this->assertSame('Ersatzteil', $items[1]->description);
        $this->assertSame('10.00', $items[1]->discount_amount?->getAmount());

        // Skonto aus der BR-DE-18-Note; Summen entsprechen der Neuberechnung.
        $this->assertSame('2.00', $invoice->skonto_percent?->getNumericValue());
        $this->assertSame(7, $invoice->skonto_days);
        $this->assertSame('340.00', $invoice->subtotal?->getAmount());
        $this->assertSame('404.60', $invoice->total?->getAmount());
        $this->assertNotContains('totals_recalculated_mismatch', (array) data_get($invoice->import_metadata, 'extraction.warnings'));
    }

    public function test_plain_xml_that_is_no_einvoice_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Pdf->value,
            'file' => UploadedFile::fake()->createWithContent('daten.xml', '<?xml version="1.0"?><root><foo>bar</foo></root>'),
        ])->assertRedirect()->assertSessionHas('error', __('invoice-import.error.xml_not_einvoice'));

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_zugferd_pdf_upload_uses_embedded_xml_instead_of_heuristics(): void {
        if (! app(XRechnungGenerator::class)->zugferdAvailable()
            || ! (new \ERechnungToolkit\Parsers\ZugferdPdfParser)->isAvailable()) {
            $this->markTestSkipped('ZUGFeRD-Werkzeuge (pdf-toolkit/pdfdetach) nicht verfügbar.');
        }

        $pdf = $this->sampleZugferdPdf();

        $this->actingAs($this->admin)->post(route('invoices.pdf-import.store'), [
            'customer_id' => $this->customer->sqid,
            'delivery_format' => InvoiceDeliveryFormat::Zugferd->value,
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $pdf),
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('ER-2026-0042', $invoice->number);
        $this->assertTrue((bool) data_get($invoice->import_metadata, 'extraction.structured'));
        $this->assertSame('zugferd_pdf', data_get($invoice->import_metadata, 'extraction.source_format'));
        $this->assertSame(2, $invoice->items()->count());
    }

    /**
     * Roundtrip-Fixture: echte XRechnung aus dem Ausgangs-Generator einer
     * ZWEITEN Organisation (Nummern-/Scope-Kollisionen ausgeschlossen).
     */
    private function sampleXRechnungXml(): string {
        $sourceInvoice = $this->sampleSourceInvoice();
        $xml = app(XRechnungGenerator::class)->generate($sourceInvoice);
        app()->instance('currentOrganization', $this->organization);

        return $xml;
    }

    private function sampleZugferdPdf(): string {
        $sourceInvoice = $this->sampleSourceInvoice();
        $pdf = app(XRechnungGenerator::class)->generateZugferdPdf($sourceInvoice);
        app()->instance('currentOrganization', $this->organization);
        $this->assertNotNull($pdf);

        return (string) $pdf;
    }

    private function sampleSourceInvoice(): Invoice {
        $sourceOrg = Organization::factory()->create();
        $sourceOrg->update(['settings' => ['einvoice' => [
            'seller_name' => 'Quelle GmbH',
            'street' => 'Quellweg 1',
            'zip' => '11111',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Q. Steller',
            'contact_email' => 'rechnung@quelle.example',
            'contact_phone' => '+49 30 999',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'payment_terms_days' => 14,
        ]]]);
        app()->instance('currentOrganization', $sourceOrg);

        $customer = Customer::query()->create([
            'organization_id' => $sourceOrg->id,
            'name' => 'Empfänger AG',
            'currency' => 'EUR',
            'email' => 'buchhaltung@empfaenger.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
            'buyer_reference' => '991-12345-67',
            'created_by' => $this->admin->id,
        ]);
        $invoice = Invoice::query()->create([
            'organization_id' => $sourceOrg->id,
            'customer_id' => $customer->id,
            'number' => 'ER-2026-0042',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => '2026-06-01',
            'due_on' => '2026-06-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'skonto_percent' => '2.00',
            'skonto_days' => 7,
            'payment_terms_days' => 14,
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $sourceOrg->id,
            'description' => 'Wartungspauschale',
            'quantity' => '2.000',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'position' => 1,
        ]);
        $invoice->items()->create([
            'organization_id' => $sourceOrg->id,
            'description' => 'Ersatzteil',
            'quantity' => '1.000',
            'unit' => 'Stk.',
            'unit_price' => '150.00',
            'discount_amount' => '10.00',
            'position' => 2,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh(['items', 'customer']);
    }

    private function samplePdf(): string {
        $dompdf = new Dompdf;
        $dompdf->loadHtml(<<<'HTML'
            <!doctype html><html lang="de"><body>
            <h1>Rechnung</h1>
            <p>Rechnungsnummer: PDF-2026-17</p>
            <p>Rechnungsdatum: 14.08.2026</p>
            <p>Fällig am: 28.08.2026</p>
            <p>Leitweg-ID: 991-12345-67</p>
            <p>Nettobetrag 1.000,00 EUR</p>
            <p>Umsatzsteuer 19 % 190,00 EUR</p>
            <p>Gesamtbetrag 1.190,00 EUR</p>
            <p>Zahlbar innerhalb von 14 Tagen. 2 % Skonto innerhalb 7 Tagen.</p>
            <p>IBAN: DE89 3704 0044 0532 0130 00</p>
            </body></html>
            HTML);
        $dompdf->render();

        return $dompdf->output();
    }
}
