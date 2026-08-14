<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceDeliveryFormat;
use App\Models\{Customer, Document, Invoice, User};
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
            </body></html>
            HTML);
        $dompdf->render();

        return $dompdf->output();
    }
}
