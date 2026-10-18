<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingPdfInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Invoicing\IncomingInvoiceRecognition;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1066: Lieferantenrechnungen ohne E-Rechnungsdaten — Erkennung als Vorschlag, Mehrfach-Upload. */
class IncomingPdfInvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE123456789']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function pdf(string $bodyHtml): string {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<!doctype html><html lang="de"><body>' . $bodyHtml . '</body></html>');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function test_plain_pdf_invoice_is_recognised_and_matched(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Malerbetrieb Pinsel', 'vat_id' => 'DE987654321']);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'number' => 'P-2026-017']);

        $invoice = $this->pdf(<<<'HTML'
            <p>Rechnung an: Muster GmbH, USt-IdNr. DE123456789</p>
            <h1>Rechnung</h1>
            <p>Rechnungsnummer: MP-4711</p>
            <p>Rechnungsdatum: 14.09.2026</p>
            <p>Ihr Projekt: P-2026-017</p>
            <p>Nettobetrag 1.000,00 EUR</p>
            <p>Umsatzsteuer 19 % 190,00 EUR</p>
            <p>Gesamtbetrag 1.190,00 EUR</p>
            <p>Malerbetrieb Pinsel · USt-IdNr. DE987654321 · IBAN DE89 3704 0044 0532 0130 00</p>
            HTML);
        $terms = $this->pdf('<h1>Allgemeine Geschäftsbedingungen</h1><p>Es gilt deutsches Recht.</p>');

        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), ['files' => [
            UploadedFile::fake()->createWithContent('rechnung.pdf', $invoice),
            UploadedFile::fake()->createWithContent('agb.pdf', $terms),
        ]])->assertRedirect(route('finance.incoming-invoices.index'))->assertSessionHas('success');

        $incoming = IncomingEInvoice::query()->sole();
        $this->assertTrue((bool) data_get($incoming->summary, 'unstructured'));
        $this->assertSame('MP-4711', $incoming->invoice_number);
        $this->assertSame('DE987654321', $incoming->seller_vat_id, 'Die eigene USt-IdNr. ist nie die des Ausstellers.');
        $this->assertSame('1190.00', $incoming->amount_gross?->getAmount());
        $this->assertContains($supplier->id, array_column((array) data_get($incoming->summary, 'suggestions.suppliers'), 'id'));
        $this->assertContains($project->id, array_column((array) data_get($incoming->summary, 'suggestions.projects'), 'id'));
        $this->assertContains(__('Ohne E-Rechnungsdaten aus PDF bzw. Bild erkannt — alle Werte am Original prüfen.'), (array) data_get($incoming->summary, 'deviations'));

        $this->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()->assertSee('MP-4711');
    }

    /** Befund 2026-10-18: Ein Klärfall erklärt sich — Leseweg, Textmenge, gefundene Teilwerte, Vorbelegung im Werte-Dialog. */
    public function test_unrecognised_mail_attachment_carries_a_recognition_diagnosis(): void {
        $pdf = $this->pdf(<<<'HTML'
            <h1>Rechnung</h1>
            <p>Rechnungsdatum: 14.09.2026</p>
            <p>Gesamtbetrag 119,00 EUR</p>
            HTML);

        $result = app(\App\Services\Invoicing\EInvoice\IncomingEInvoiceService::class)->storeMessage(
            $this->admin,
            [new \App\Services\Mail\MailAttachment('beleg.pdf', 'application/pdf', $pdf)],
            new \App\Services\Invoicing\EInvoice\IncomingOrigin('lieferant@example.test', '<diag@example.test>'),
        );
        $this->assertSame(1, $result['unrecognized']);

        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(IncomingInvoiceRecognition::None, $incoming->recognition);
        $this->assertNull($incoming->invoice_number);
        $extraction = (array) data_get($incoming->summary, 'extraction');
        $this->assertSame('no_number', $extraction['reason']);
        $this->assertSame('pdftotext', $extraction['reader']);
        $this->assertGreaterThan(0, $extraction['text_length']);
        $this->assertSame(['issued_on' => '2026-09-14', 'gross' => '119.00'], $extraction['found']);
        $this->assertStringContainsString('keine Rechnungsnummer bei einer bekannten Beschriftung', implode(' ', (array) data_get($incoming->summary, 'deviations')));

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))
            ->assertOk()->assertSee(__('Erkennungsdiagnose'))->assertSee('119,00');
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.values.form', $incoming))
            ->assertOk()->assertSee('value="119.00"', false)->assertSee('value="2026-09-14"', false);
    }

    public function test_single_unreadable_file_is_rejected(): void {
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => UploadedFile::fake()->createWithContent('agb.pdf', $this->pdf('<p>Nur Text ohne Rechnung.</p>')),
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, IncomingEInvoice::query()->count());
    }

    /**
     * Befund 2026-10-18: Absender melden PDFs als `application/x-download`,
     * und vor `%PDF-` darf Vorspann stehen. Der Präfix-Sniff hielt solche
     * PDFs für XML (`<<` steht in jedem PDF-Kopf) — der E-Rechnungs-Parser
     * schrieb bei jeder Nacherkennung „Failed to parse XML“ ins Fehlerlog,
     * und extractXml() gab die PDF-Bytes als Rechnungs-XML zurück.
     */
    public function test_content_not_declared_mime_decides_between_pdf_and_xml(): void {
        $service = app(IncomingEInvoiceService::class);
        $pdf = "\r\n" . $this->pdf('<h1>Rechnung</h1><p>Rechnungsnummer: RE-2026-0815</p>');
        $log = Log::spy();

        $this->assertNull($service->parse($pdf, 'application/x-download'));
        $this->assertNull($service->extractXml($pdf, 'application/x-download'), 'PDF-Bytes sind kein Rechnungs-XML.');
        $log->shouldNotHaveReceived('log', ['error', Mockery::on(static fn (string $message): bool => str_contains($message, 'Failed to parse XML')), Mockery::any()]);

        // XML ohne Prolog meldet finfo als Text — die erste Klammer entscheidet, auch hinter BOM und Leerraum.
        $xml = "\xEF\xBB\xBF\n  <Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\"><ID>1</ID></Invoice>";
        $this->assertSame($xml, $service->extractXml($xml, 'text/plain'));
        // Gemeldeter Typ zählt nur, wenn der Inhalt nichts hergibt.
        $this->assertNull($service->extractXml('kein Beleg', 'text/plain'));
    }
}
