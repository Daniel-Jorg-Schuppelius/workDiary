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

    /** Das Original steht neben den erkannten Werten, auf der Detailseite und im Korrektur-Dialog. */
    public function test_pdf_original_is_previewed_next_to_the_recognised_values(): void {
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $this->pdf(<<<'HTML'
                <h1>Rechnung</h1>
                <p>Rechnungsnummer: MP-4712</p>
                <p>Rechnungsdatum: 14.09.2026</p>
                <p>Gesamtbetrag 238,00 EUR</p>
                HTML)),
        ])->assertRedirect();
        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(IncomingInvoiceRecognition::Extracted, $incoming->recognition);
        $previewUrl = route('finance.incoming-invoices.preview', $incoming->document);

        $this->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee('<iframe src="' . $previewUrl . '#view=FitH&amp;navpanes=0"', false)
            ->assertSee(__('Werte korrigieren'))
            ->assertSee(__('Korrigieren'));
        $this->get($previewUrl)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="rechnung.pdf"')
            ->assertHeader('Content-Security-Policy', \App\Http\Middleware\SecurityHeaders::DOCUMENT_CSP);
        $this->get(route('finance.incoming-invoices.values.form', $incoming))->assertOk()
            ->assertSee(__('Werte korrigieren'))
            ->assertSee('wd-dialog--wide', false)
            ->assertSee('<iframe src="' . $previewUrl . '#view=FitH&amp;navpanes=0"', false);
    }

    /** Ein Klärfall zeigte erfasste Werte nicht an, weil seine Erkennung „keine“ bleibt. */
    public function test_captured_values_of_a_clarification_case_show_on_the_detail_page(): void {
        app(IncomingEInvoiceService::class)->storeMessage(
            $this->admin,
            [new \App\Services\Mail\MailAttachment('beleg.pdf', 'application/pdf', $this->pdf('<h1>Rechnung</h1><p>Gesamtbetrag 119,00 EUR</p>'))],
            new \App\Services\Invoicing\EInvoice\IncomingOrigin('lieferant@example.test', '<klaerfall@example.test>'),
        );
        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(IncomingInvoiceRecognition::None, $incoming->recognition);
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.values.form', $incoming))->assertOk()->assertSee(__('Werte erfassen'));

        $this->post(route('finance.incoming-invoices.values', $incoming), [
            'invoice_number' => 'KF-2026-0099',
            'issue_date' => '2026-09-14',
            'currency' => 'EUR',
            'amount_net' => '100.00',
            'amount_tax' => '19.00',
            'amount_gross' => '119.00',
        ])->assertRedirect();

        $this->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee(__('Rechnungsdaten (von Hand erfasst)'))
            ->assertSee('KF-2026-0099')
            ->assertSee('100,00')
            ->assertDontSee(__('Keine Rechnungsdaten erkannt (Klärfall).'))
            ->assertDontSee(__('Das Original konnte nicht (mehr) als E-Rechnung geparst werden.'));
        $this->get(route('finance.incoming-invoices.values.form', $incoming))->assertOk()->assertSee(__('Werte korrigieren'));
    }

    public function test_preview_of_a_confidential_original_is_audited_and_foreign_access_denied(): void {
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $this->pdf('<h1>Rechnung</h1><p>Rechnungsnummer: MP-4713</p><p>Gesamtbetrag 119,00 EUR</p>')),
        ])->assertRedirect();
        $document = IncomingEInvoice::query()->sole()->document;
        $author = User::factory()->create(['organization_id' => $this->organization->id]);
        $document->forceFill(['confidential' => true, 'created_by_user_id' => $author->id])->save();

        $this->get(route('finance.incoming-invoices.preview', $document))->assertOk();
        $this->assertSame(1, \App\Models\Audit\AuditLog::query()
            ->where('event', 'document.confidentialAccessed')->where('auditable_id', $document->id)->where('user_id', $this->admin->id)->count());

        $foreign = User::factory()->admin()->create(['organization_id' => \App\Models\Platform\Organization::factory()->create()->id]);
        $this->assertContains($this->actingAs($foreign)->get(route('finance.incoming-invoices.preview', $document))->status(), [403, 404]);
    }

    public function test_preview_type_follows_the_content_of_the_original(): void {
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', true);

        $this->assertSame('application/pdf', IncomingEInvoiceService::previewMime($this->pdf('<p>Rechnung</p>')));
        $this->assertSame('image/png', IncomingEInvoiceService::previewMime($png));
        $this->assertSame('application/pdf', IncomingEInvoiceService::previewMime($this->tiff(8, 8)), 'TIFF zeigen Browser nicht an, es kommt als PDF.');
        $this->assertNull(IncomingEInvoiceService::previewMime('<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>'), 'XML läuft über die Sichtdarstellung.');
        $this->assertNull(IncomingEInvoiceService::previewMime(''));
        $this->assertSame($png, IncomingEInvoiceService::previewBody($png));
    }

    public function test_tiff_scan_is_previewed_as_pdf_without_touching_the_original(): void {
        $tiff = $this->tiff(200, 200);
        app(IncomingEInvoiceService::class)->storeMessage(
            $this->admin,
            [new \App\Services\Mail\MailAttachment('scan.tif', 'image/tiff', $tiff)],
            new \App\Services\Invoicing\EInvoice\IncomingOrigin('lieferant@example.test', '<tiff@example.test>'),
        );
        $incoming = IncomingEInvoice::query()->sole();
        $previewUrl = route('finance.incoming-invoices.preview', $incoming->document);

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee('<iframe src="' . $previewUrl . '#view=FitH&amp;navpanes=0"', false);
        $response = $this->get($previewUrl)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="scan.pdf"');
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        $this->assertSame($tiff, \Illuminate\Support\Facades\Storage::disk('local')->get((string) $incoming->document?->currentVersion?->path), 'Das abgelegte Original bleibt unverändert.');
    }

    public function test_unconvertible_tiff_shows_a_hint_instead_of_an_error_page(): void {
        $broken = "II*\0" . str_repeat("\0", 30_000);
        $this->assertNull(IncomingEInvoiceService::previewBody($broken));
        app(IncomingEInvoiceService::class)->storeMessage(
            $this->admin,
            [new \App\Services\Mail\MailAttachment('kaputt.tif', 'image/tiff', $broken)],
            new \App\Services\Invoicing\EInvoice\IncomingOrigin('lieferant@example.test', '<kaputt@example.test>'),
        );
        $incoming = IncomingEInvoice::query()->sole();

        $response = $this->actingAs($this->admin)->get(route('finance.incoming-invoices.preview', $incoming->document))->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee(__('Keine Vorschau möglich'));
        $this->assertStringStartsWith('sandbox;', (string) $response->headers->get('Content-Security-Policy'));
    }

    /** Unkomprimiertes Graustufen-TIFF (Baseline, Little Endian) mit dunklem Rahmen. */
    private function tiff(int $width, int $height): string {
        $short = static fn (int $tag, int $value): string => pack('vvVv', $tag, 3, 1, $value) . "\0\0";
        $long = static fn (int $tag, int $value): string => pack('vvVV', $tag, 4, 1, $value);
        $rational = static fn (int $tag, int $offset): string => pack('vvVV', $tag, 5, 1, $offset);
        $entries = 12;
        $rationals = 8 + 2 + $entries * 12 + 4;
        $data = $rationals + 16;
        $ifd = pack('v', $entries)
            . $short(256, $width) . $short(257, $height) . $short(258, 8) . $short(259, 1) . $short(262, 1)
            . $long(273, $data) . $short(277, 1) . $long(278, $height) . $long(279, $width * $height)
            . $rational(282, $rationals) . $rational(283, $rationals + 8) . $short(296, 2)
            . pack('V', 0);
        $pixels = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixels .= chr($x < 4 || $y < 4 || $x >= $width - 4 || $y >= $height - 4 ? 0 : 255);
            }
        }

        return 'II' . pack('vV', 42, 8) . $ifd . pack('VVVV', 72, 1, 72, 1) . $pixels;
    }
}
