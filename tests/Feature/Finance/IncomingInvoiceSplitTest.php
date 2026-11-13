<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceSplitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Document\DocumentService;
use App\Services\Invoicing\EInvoice\IncomingInvoiceTransferGate;
use App\Services\Invoicing\GiroCode\GiroCodeReader;
use App\Services\Invoicing\Recognition\Contracts\QrCodeSource;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\File;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PDFToolkit\Helper\{PDFHelper, PDFSplitHelper};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sammel-PDF im Rechnungseingang (Feature 208, MVP-1662) mit erfundenen
 * Rechnungen: Grenzvorschlag, Aufteilen je Rechnung mit Teil-PDF, Original
 * unverändert, Dubletten je Teil, Löschschutz.
 */
class IncomingInvoiceSplitTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        if (! PDFSplitHelper::isPageExtractionAvailable() || PDFHelper::getPageCount(self::fixturePath()) < 1) {
            $this->markTestSkipped('PDF-Werkzeuge (pdfinfo, mutool/pdftk/poppler) fehlen.');
        }
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE123456789']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        // Kein QR-Lesen in diesen Tests: der GiroCode hat eigene Tests.
        $this->app->instance(QrCodeSource::class, new class implements QrCodeSource {
            public function isAvailable(): bool {
                return true;
            }

            public function scanPdf(string $pdfPath, array $pages, int $dpi): array {
                return [];
            }

            public function scanImage(string $imagePath): array {
                return [];
            }
        });
    }

    /** Eine einseitige Dompdf-Datei als Werkzeugprobe. */
    private static function fixturePath(): string {
        static $path = null;
        if ($path === null) {
            $path = File::createTemp(self::render(['<p>Probe</p>']), 'split-probe-', 'pdf');
        }

        return $path;
    }

    /** @param  list<string>  $pages */
    private static function render(array $pages): string {
        $dompdf = new Dompdf;
        $body = implode('<div style="page-break-after: always"></div>', $pages);
        $dompdf->loadHtml('<!doctype html><html lang="de"><body>' . $body . '</body></html>');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private static function invoice(string $number, string $seller, string $vat, string $gross): string {
        return <<<HTML
            <p>{$seller} · Musterweg 1 · 12345 Musterstadt</p>
            <p>Rechnung an: Muster GmbH, USt-IdNr. DE123456789</p>
            <h1>Rechnung</h1>
            <p>Rechnungsnummer: {$number}</p>
            <p>Rechnungsdatum: 02.11.2026</p>
            <p>Nettobetrag 100,00 EUR</p>
            <p>Umsatzsteuer 19 % 19,00 EUR</p>
            <p>Gesamtbetrag {$gross} EUR</p>
            <p>{$seller} · USt-IdNr. {$vat}</p>
            <p>Seite 1 von 1</p>
            HTML;
    }

    private function threeInvoices(): string {
        return self::render([
            self::invoice('MP-1001', 'Malerbetrieb Pinsel', 'DE987654321', '119,00'),
            self::invoice('MP-1002', 'Malerbetrieb Pinsel', 'DE987654321', '119,00'),
            self::invoice('DD-77', 'Dachdeckerei Ziegel', 'DE876543210', '119,00'),
        ]);
    }

    /** @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function upload(string $pdf, string $name = 'sammel.pdf'): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => UploadedFile::fake()->createWithContent($name, $pdf),
        ]);
    }

    public function test_three_invoices_become_three_incomings_and_the_original_stays_unchanged(): void {
        $pdf = $this->threeInvoices();
        $this->upload($pdf)->assertRedirect();

        $container = IncomingEInvoice::query()->sole();
        $original = $container->document;
        $this->assertNotNull($original);
        $this->assertSame(IncomingInvoiceSplitter::PROPOSED, $container->splitState(), 'Der Job schlägt drei Rechnungen vor.');
        $this->assertSame([1, 2, 3], data_get($container->split_proposal, 'starts'));
        $this->assertContains((string) __('incoming_pdf.split.gate'), app(IncomingInvoiceTransferGate::class)->blockers($container), 'Übergabe wartet auf die Entscheidung.');

        $this->get(route('finance.incoming-invoices.show', $original))->assertOk()
            ->assertSee(__('incoming_pdf.split.action.open'));
        $this->get(route('finance.incoming-invoices.split.form', $container))->assertOk()
            ->assertSee(__('incoming_pdf.split.dialog.page', ['page' => 3]));
        if ((bool) ($container->split_proposal['images'] ?? false)) {
            $this->get(route('finance.incoming-invoices.split.page', [$container, 2]))->assertOk()->assertHeader('Content-Type', 'image/png');
        }
        $this->get(route('finance.incoming-invoices.split.page', [$container, 4]))->assertNotFound();

        $this->post(route('finance.incoming-invoices.split', $container), ['starts' => ['2', '3']])
            ->assertRedirect(route('finance.incoming-invoices.index'));

        // Der Sammel-Eingang bleibt als Nachweis (GoBD), aber in keiner Arbeitsliste (Sicherheitsprüfung N6).
        $container->refresh();
        $this->assertSame(IncomingEInvoiceStatus::Split, $container->status);
        $this->assertSame(IncomingInvoiceSplitter::SPLIT, $container->splitState());
        $this->assertCount(3, (array) data_get($container->split_proposal, 'parts'));
        $this->assertSame(3, IncomingEInvoice::query()->openForWork()->count());
        $this->assertSame(3, IncomingEInvoice::query()->awaitingAssignment()->count(), 'Arbeitsliste „Zuzuordnen“: drei Eingänge.');
        $this->assertContains((string) __('incoming_pdf.split.blocked.split'), app(IncomingInvoiceTransferGate::class)->blockers($container));
        $parts = IncomingEInvoice::query()->whereNotNull('source_document_id')->orderBy('source_page_from')->get();
        $this->assertCount(3, $parts);
        $this->assertSame(['MP-1001', 'MP-1002', 'DD-77'], $parts->pluck('invoice_number')->all());
        $this->assertSame([[1, 1], [2, 2], [3, 3]], $parts->map(static fn (IncomingEInvoice $part): array => [$part->source_page_from, $part->source_page_to])->all());
        foreach ($parts as $part) {
            $this->assertSame($original->id, $part->source_document_id);
            $this->assertSame($container->sha256, $part->source_sha256);
            $this->assertNotSame($original->id, $part->document_id, 'Jede Rechnung hat ihre eigene Teil-PDF.');
            $this->assertNull($part->duplicate_of_id, 'Der aufgelöste Sammel-Eingang ist kein Doppel der ersten Rechnung.');
            $this->assertTrue($part->received_at->equalTo($container->received_at), 'Teile übernehmen die Eingangszeit des Originals.');
            $stored = $part->storedOriginal();
            $this->assertNotNull($stored);
            $this->assertSame($part->sha256, CryptoHelper::hash($stored['contents']));
            $this->assertSame(1, File::withTemp($stored['contents'], static fn (string $path): int => PDFHelper::getPageCount($path), 'part-', 'pdf'));
            $this->assertStringContainsString($original->title, (string) $part->document?->description);
        }

        // Das Original bleibt mit gleichem Hash erhalten und zeigt seine Teile.
        $original->refresh();
        $version = $original->currentVersion;
        $this->assertNotNull($version);
        $this->assertSame($container->sha256, CryptoHelper::hash((string) Storage::disk('local')->get((string) $version->path)));
        $this->assertSame(1, $original->versions()->count());
        $this->get(route('finance.incoming-invoices.show', $original))->assertOk()
            ->assertSee(__('incoming_pdf.split.parts.title'))->assertSee('DD-77');
        $secondPart = $parts->firstWhere('source_page_from', 2);
        $this->assertNotNull($secondPart?->document);
        $this->get(route('finance.incoming-invoices.show', $secondPart->document))->assertOk()
            ->assertSee(__('incoming_pdf.split.open_original'));
        $this->assertSame(1, $original->auditLogs()->where('event', 'document.invoice_split')->count());

        // Dieselbe Sammel-PDF noch einmal: Dublette, kein vierter Eingang.
        $this->upload($pdf, 'nochmal.pdf')->assertSessionHas('error');
        $this->assertSame(4, IncomingEInvoice::query()->count(), 'Drei Teile und der Sammel-Eingang als Nachweis.');
        $this->assertSame(3, IncomingEInvoice::query()->openForWork()->count());
    }

    public function test_reuploaded_part_is_a_duplicate_by_hash_and_by_number_and_sender(): void {
        $this->upload($this->threeInvoices())->assertRedirect();
        $container = IncomingEInvoice::query()->sole();
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $container), ['starts' => ['2', '3']]);
        $second = IncomingEInvoice::query()->where('invoice_number', 'MP-1002')->sole();
        $stored = $second->storedOriginal();
        $this->assertNotNull($stored);

        // Die Teil-PDF selbst noch einmal hochgeladen: Hash-Dublette.
        $this->upload($stored['contents'], 'teil.pdf')
            ->assertRedirect(route('finance.incoming-invoices.show', $second->document))->assertSessionHas('error');
        $this->assertSame(3, IncomingEInvoice::query()->openForWork()->count());

        // Dieselbe Rechnung als eigene Datei: Rechnungsnummer und Absender machen sie zum möglichen Doppel.
        $this->upload(self::render([self::invoice('MP-1002', 'Malerbetrieb Pinsel', 'DE987654321', '119,00') . '<p>Kopie</p>']), 'kopie.pdf')->assertRedirect();
        $copy = IncomingEInvoice::query()->whereNull('source_document_id')->openForWork()->sole();
        $this->assertSame($second->id, $copy->duplicate_of_id);
        $this->assertTrue($copy->isPossibleDuplicate());
    }

    /** Sicherheitsprüfung N5: geschützt, solange überhaupt ein Teil besteht — nicht erst, wenn er gebucht ist. */
    public function test_original_cannot_be_deleted_while_parts_exist(): void {
        $this->upload($this->threeInvoices())->assertRedirect();
        $container = IncomingEInvoice::query()->sole();
        $original = $container->document;
        $this->assertNotNull($original);
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $container), ['starts' => ['2', '3']]);

        try {
            app(DocumentService::class)->delete($original->refresh(), $this->admin);
            $this->fail('Das Original einer aufgeteilten Sammel-PDF darf nicht gelöscht werden.');
        } catch (ValidationException $e) {
            $this->assertSame((string) __('incoming_pdf.split.error.original_protected'), $e->errors()['document'][0] ?? null);
        }
        $this->assertNotSoftDeleted($original);
        $this->assertThrows(fn () => app(DocumentService::class)->purge($original->refresh(), $this->admin, 'Test'), ValidationException::class);
        $this->get(route('finance.incoming-invoices.show', $original))->assertOk()->assertSee(__('incoming_pdf.split.parts.protected'));

        // Aufbewahrungs-Scan: auch archiviert kein Löschvorschlag, solange Teile bestehen.
        $original->forceFill(['status' => \App\Enums\Document\DocumentStatus::Archived])->save();
        $policy = collect(app(\App\Services\Document\Retention\DocumentRetentionPolicies::class)->policies())->firstWhere('area', 'documents_invoice');
        $this->assertNotNull($policy);
        $exempt = $policy->exempt;
        $this->assertNotNull($exempt);
        $this->assertNotNull($exempt($original->refresh()));

        // Datenbank: der Verweis der Teile wird beim harten Löschen nicht still geleert.
        $this->assertThrows(fn () => \Illuminate\Support\Facades\DB::table('documents')->where('id', $original->id)->delete(), \Illuminate\Database\QueryException::class);
        $this->assertSame(3, IncomingEInvoice::query()->where('source_document_id', $original->id)->count());
    }

    public function test_one_invoice_can_be_kept_and_releases_the_gate(): void {
        $this->upload(self::render([
            self::invoice('MP-2001', 'Malerbetrieb Pinsel', 'DE987654321', '119,00'),
            self::invoice('MP-2002', 'Malerbetrieb Pinsel', 'DE987654321', '119,00'),
        ]))->assertRedirect();
        $container = IncomingEInvoice::query()->sole();
        $this->assertTrue(IncomingInvoiceSplitter::isHolding($container));
        $this->assertSame(GiroCodeReader::PENDING, data_get($container->girocode, 'status'), 'Der GiroCode einer Sammel-PDF wird erst am Teil gelesen.');

        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split.dismiss', $container))->assertRedirect();

        $container->refresh();
        $this->assertSame(IncomingInvoiceSplitter::DISMISSED, $container->splitState());
        $this->assertFalse(IncomingInvoiceSplitter::isHolding($container));
        $this->assertNotContains((string) __('incoming_pdf.split.gate'), app(IncomingInvoiceTransferGate::class)->blockers($container));
        $this->assertSame(GiroCodeReader::NONE, data_get($container->girocode, 'status'), 'Nach der Entscheidung liest der Job den GiroCode.');
        $this->assertSame(1, $container->auditLogs()->where('event', 'incoming_einvoice.split_dismissed')->count());
    }

    public function test_single_invoice_over_two_pages_is_not_split(): void {
        $this->upload(self::render([
            '<h1>Rechnung</h1><p>Rechnungsnummer: MP-3001</p><p>Rechnungsdatum: 02.11.2026</p><p>Position 1</p><p>Seite 1 von 2</p>',
            '<p>Rechnungsnummer: MP-3001</p><p>Nettobetrag 100,00 EUR</p><p>Umsatzsteuer 19 % 19,00 EUR</p><p>Gesamtbetrag 119,00 EUR</p><p>Malerbetrieb Pinsel · USt-IdNr. DE987654321</p><p>Seite 2 von 2</p>',
        ]))->assertRedirect();

        $incoming = IncomingEInvoice::query()->sole();
        $this->assertSame(IncomingInvoiceSplitter::SINGLE, $incoming->splitState());
        $this->assertFalse(IncomingInvoiceSplitter::isHolding($incoming));
        $this->assertSame(GiroCodeReader::NONE, data_get($incoming->girocode, 'status'));
    }

    public function test_split_is_refused_for_bad_pages_wrong_status_and_without_billing_rights(): void {
        $this->upload($this->threeInvoices())->assertRedirect();
        $container = IncomingEInvoice::query()->sole();

        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $container), ['starts' => ['5']])
            ->assertSessionHas('error', (string) __('incoming_pdf.split.error.invalid_page'));
        $this->post(route('finance.incoming-invoices.split', $container), ['starts' => ['999']])->assertSessionHasErrors('starts.0');
        $this->post(route('finance.incoming-invoices.split', $container), ['starts' => ['1']])
            ->assertSessionHas('error', (string) __('incoming_pdf.split.error.single'));

        $clerk = $this->orgUser();
        $this->actingAs($clerk)->post(route('finance.incoming-invoices.split', $container), ['starts' => ['2']])->assertForbidden();
        $this->actingAs($clerk)->get(route('finance.incoming-invoices.split.page', [$container, 1]))->assertForbidden();

        $container->forceFill(['status' => IncomingEInvoiceStatus::Approved])->save();
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $container), ['starts' => ['2']])
            ->assertSessionHas('error', (string) __('incoming_pdf.split.blocked.status'));
        $this->assertSame(1, IncomingEInvoice::query()->count());
    }

    public function test_changed_original_is_not_split(): void {
        $this->upload($this->threeInvoices())->assertRedirect();
        $container = IncomingEInvoice::query()->sole();
        $version = $container->document?->currentVersion;
        $this->assertNotNull($version);
        Storage::disk('local')->put((string) $version->path, self::render(['<p>Untergeschoben</p>', '<p>Zweite Seite</p>']));

        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $container), ['starts' => ['2']])
            ->assertSessionHas('error', (string) __('incoming_pdf.split.error.original_changed'));

        $container->refresh();
        $this->assertSame(IncomingInvoiceSplitter::PROPOSED, $container->splitState(), 'Zurück auf den Vorschlag, mit Meldung.');
        $this->assertSame(1, IncomingEInvoice::query()->count());
    }

    public function test_other_organization_cannot_reach_the_split(): void {
        $this->upload($this->threeInvoices())->assertRedirect();
        $container = IncomingEInvoice::query()->sole();

        $foreign = \App\Models\Platform\Organization::factory()->create();
        $stranger = User::factory()->admin()->create(['organization_id' => $foreign->id]);
        app()->instance('currentOrganization', $foreign);

        $this->actingAs($stranger)->get(route('finance.incoming-invoices.split.form', $container))->assertNotFound();
        $this->actingAs($stranger)->post(route('finance.incoming-invoices.split', $container), ['starts' => ['2']])->assertNotFound();
    }

    public function test_pdf_over_the_page_limit_is_not_checked(): void {
        $pages = array_fill(0, IncomingInvoiceSplitter::MAX_PAGES + 1, '<p>Seite</p>');

        $state = IncomingInvoiceSplitter::initialState(self::render($pages), \App\Enums\Invoicing\IncomingInvoiceRecognition::Extracted);

        $this->assertSame(['state' => IncomingInvoiceSplitter::TOO_LARGE, 'pages' => IncomingInvoiceSplitter::MAX_PAGES + 1], $state);
        $this->assertNull(IncomingInvoiceSplitter::initialState(self::render(['<p>Eine Seite</p>']), \App\Enums\Invoicing\IncomingInvoiceRecognition::Extracted));
        $this->assertNull(IncomingInvoiceSplitter::initialState('<Invoice/>', \App\Enums\Invoicing\IncomingInvoiceRecognition::Structured));
    }
}
