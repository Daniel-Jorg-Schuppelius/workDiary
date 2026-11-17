<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingPdfHardeningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Jobs\Invoicing\{AnalyzeIncomingInvoicePagesJob, SplitIncomingInvoiceJob};
use App\Models\Finance\{BankAccount, IncomingInvoiceRetention};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Billing\Sepa\{PaymentProposalService, PaymentRunService};
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceParties};
use App\Services\Invoicing\GiroCode\{GiroCodeCheck, GiroCodeReader};
use App\Services\Invoicing\InvoicePdfImportService;
use App\Services\Invoicing\Recognition\Contracts\{PagePreviewSource, QrCodeSource, WordBoxSource};
use App\Services\Invoicing\Recognition\{PageImages, ToolkitPageImageSource, ToolkitPagePreviewSource};
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use CommonToolkit\Builders\Payment\EpcQrBuilder;
use CommonToolkit\Helper\Data\CryptoHelper;
use Composer\InstalledVersions;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Bus, DB, RateLimiter, Storage};
use PDFToolkit\Helper\PDFSplitHelper;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Regressionstests der Sicherheitsprüfung Feature 208 (Sammel-PDF und
 * GiroCode im Rechnungseingang) — je Befund ein Test, mit erfundenen
 * Rechnungen, Beispiel-IBANs aus Dokumentationen und gefälschten Scans.
 */
class IncomingPdfHardeningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string MASTER_IBAN = 'DE89370400440532013000';

    private const string OTHER_IBAN = 'DE02120300000000202051';

    private User $admin;

    /** @var array{codes: array<int, list<array{type: string, data: string}>>, calls: int} */
    public array $scan = ['codes' => [], 'calls' => 0];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE123456789']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $test = $this;
        $this->app->instance(QrCodeSource::class, new class($test) implements QrCodeSource {
            public function __construct(private readonly IncomingPdfHardeningTest $test) {}

            public function isAvailable(): bool {
                return true;
            }

            public function scanPdf(string $pdfPath, array $pages, int $dpi): array {
                $this->test->scan['calls']++;

                return $pages === [] ? [] : array_intersect_key($this->test->scan['codes'], array_flip($pages));
            }

            public function scanImage(string $imagePath): array {
                return $this->test->scan['codes'][1] ?? [];
            }
        });
    }

    private static function payload(string $iban, string $name = 'Malerbetrieb Pinsel'): string {
        return EpcQrBuilder::to($name, $iban)->amount('119.00')->remittance('MP-4711')->build();
    }

    /** @param  list<string>  $pages */
    private static function pdf(array $pages): string {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<!doctype html><html lang="de"><body>' . implode('<div style="page-break-after: always"></div>', $pages) . '</body></html>');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private static function invoiceHtml(string $number = 'MP-4711', ?string $iban = null): string {
        $bank = $iban === null ? '' : '<p>Bankverbindung: IBAN ' . $iban . '</p>';

        return '<p>Rechnung an: Muster GmbH, USt-IdNr. DE123456789</p><h1>Rechnung</h1><p>Rechnungsnummer: ' . $number . '</p>'
            . '<p>Rechnungsdatum: 02.11.2026</p><p>Nettobetrag 100,00 EUR</p><p>Umsatzsteuer 19 % 19,00 EUR</p>'
            . '<p>Gesamtbetrag 119,00 EUR</p><p>Malerbetrieb Pinsel · USt-IdNr. DE987654321</p>' . $bank . '<p>Seite 1 von 1</p>';
    }

    private function receive(string $pdf, string $name = 'rechnung.pdf'): IncomingEInvoice {
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => UploadedFile::fake()->createWithContent($name, $pdf),
        ])->assertRedirect();

        return IncomingEInvoice::query()->latest('id')->firstOrFail();
    }

    /**
     * Eingang mit abgelegtem Original, ohne Erkennung und Job.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function stored(string $contents, array $attributes = []): IncomingEInvoice {
        Bus::fake([\App\Jobs\ExtractDocumentTextJob::class]);
        $document = app(\App\Services\Document\DocumentService::class)->createFromContents(null, $this->admin, [
            'title' => 'Probe', 'document_type' => \App\Enums\Document\DocumentType::Invoice->value,
        ], $contents, 'probe.pdf', 'application/pdf');

        $guarded = array_intersect_key($attributes, array_flip(['split_proposal', 'girocode']));
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id,
            'document_id' => $document->id,
            'sha256' => (string) CryptoHelper::hash($contents),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::Received,
            'recognition' => IncomingInvoiceRecognition::None,
            ...array_diff_key($attributes, $guarded),
        ]);
        if ($guarded !== []) {
            $incoming->forceFill($guarded)->save();
        }

        return $incoming;
    }

    private function supplier(?string $iban = null): Supplier {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Malerbetrieb Pinsel', 'vat_id' => 'DE987654321']);
        if ($iban !== null) {
            $supplier->bankAccounts()->create(['organization_id' => $this->organization->id, 'account_holder' => 'Malerbetrieb Pinsel', 'iban' => $iban, 'is_primary' => true]);
        }

        return $supplier;
    }

    private function account(): BankAccount {
        return BankAccount::factory()->create(['organization_id' => $this->organization->id, 'iban' => 'DE75512108001245126199', 'is_active' => true]);
    }

    /** @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> */
    private function confirm(IncomingEInvoice $invoice, ?string $token = null): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)->post(route('finance.payment-runs.proposals.confirm-iban', ['invoice' => $invoice]), [
            'token' => $token ?? app(PaymentProposalService::class)->confirmationToken($invoice),
        ]);
    }

    /**
     * H1: CropBox A4, MediaBox 9000 × 9000 pt — pdfinfo meldet A4, pdftoppm rastert die MediaBox. Geprüft wird
     * die größere der beiden Boxen (pdf-toolkit 0.32.1, `getAllPageBoxes`); die Box-Bombe geht nie ans Raster.
     */
    public function test_crop_box_bomb_is_never_rasterized(): void {
        $this->assertTrue(version_compare(ltrim((string) InstalledVersions::getPrettyVersion('daniel-jorg-schuppelius/php-pdf-toolkit'), 'v'), '0.32.1', '>='));
        $bomb = (string) file_get_contents(base_path('tests/Fixtures/invoicing/cropbomb9000.pdf'));
        $this->scan['codes'][1] = [['type' => 'QR-Code', 'data' => self::payload(self::MASTER_IBAN)]];

        $single = $this->stored($bomb);
        app(GiroCodeReader::class)->apply($single);
        $this->assertSame(0, $this->scan['calls'], 'Keine Seite der Box-Bombe geht ans Raster.');
        $this->assertSame(GiroCodeReader::UNREADABLE, data_get($single->refresh()->girocode, 'status'));
        $this->assertSame('page_size', data_get($single->girocode, 'reason'));
        $this->assertFalse(IncomingInvoiceSplitter::pagesWithinLimits(base_path('tests/Fixtures/invoicing/cropbomb9000.pdf')));
        $this->assertFalse(IncomingInvoiceSplitter::pagesWithinLimits(base_path('tests/Fixtures/invoicing/missing.pdf')), 'Ohne lesbare Maße wird nicht gerastert.');
        // Auch die Raster-Adapter (Zonen-Editor im Request, Vorschau) prüfen vorher — ohne pdftoppm-Lauf.
        $out = storage_path('framework/testing/cropbomb-' . uniqid());
        $this->assertSame([], (new ToolkitPageImageSource)->render(base_path('tests/Fixtures/invoicing/cropbomb9000.pdf'), $out, 1, 1, PageImages::DPI));
        $this->assertSame([], (new ToolkitPagePreviewSource)->render(base_path('tests/Fixtures/invoicing/cropbomb9000.pdf'), $out, 1, 1, IncomingInvoiceSplitter::IMAGE_DPI));
        $this->assertDirectoryDoesNotExist($out);

        $bundle = $this->stored($bomb . "\n%x", ['split_proposal' => ['state' => IncomingInvoiceSplitter::PENDING, 'pages' => 4]]);
        app(IncomingInvoiceSplitter::class)->analyze($bundle);
        $this->assertSame(IncomingInvoiceSplitter::TOO_LARGE, $bundle->refresh()->splitState());
    }

    /** M1: Erkennung je Teil (OCR, KI-Rückfall) nie innerhalb einer Transaktion; bei Rückabwicklung keine verwaisten Dateien. */
    public function test_part_recognition_runs_outside_the_transaction_and_rollback_removes_files(): void {
        $baseline = DB::transactionLevel();
        /** @var \ArrayObject<int, int> $levels */
        $levels = new \ArrayObject();
        $this->app->instance(InvoicePdfImportService::class, new class($levels) extends InvoicePdfImportService {
            /** @param  \ArrayObject<int, int>  $levels */
            public function __construct(private readonly \ArrayObject $levels) {}

            public function extract(string $path, string $extension = 'pdf', ?string $mime = null, ?\App\Models\Platform\Organization $organization = null, bool $structured = true): array {
                $this->levels->append(DB::transactionLevel());

                return parent::extract($path, $extension, $mime, $organization, $structured);
            }
        });
        $container = $this->receive(self::pdf([self::invoiceHtml('MP-1'), self::invoiceHtml('MP-2'), self::invoiceHtml('MP-3')]), 'sammel.pdf');
        $this->assertSame(IncomingInvoiceSplitter::PROPOSED, $container->splitState());
        $levels->exchangeArray([]);

        // Der zweite Teil scheitert beim Ablegen: alles wird zurückgenommen, auch die Datei des ersten Teils.
        /** @var \ArrayObject<int, string> $written */
        $written = new \ArrayObject();
        $this->app->instance(IncomingEInvoiceService::class, new class($written) extends IncomingEInvoiceService {
            private int $calls = 0;

            /** @param  \ArrayObject<int, string>  $written */
            public function __construct(private readonly \ArrayObject $written) {}

            public function storePart(User $actor, IncomingEInvoice $container, string $contents, array $summary, string $fileName, int $from, int $to): array {
                if (++$this->calls === 2) {
                    throw new RuntimeException('Testfehler beim zweiten Teil');
                }
                $stored = parent::storePart($actor, $container, $contents, $summary, $fileName, $from, $to);
                $this->written->append((string) $stored['incoming']?->document?->currentVersion?->path);

                return $stored;
            }
        });
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $container), ['starts' => ['2', '3']])->assertSessionHas('error');

        $this->assertNotSame([], $levels->getArrayCopy());
        $this->assertSame([$baseline], array_values(array_unique($levels->getArrayCopy())), 'OCR/KI-Rückfall laufen ohne offene Transaktion.');
        $this->assertCount(1, $written);
        $this->assertNotSame('', $written[0]);
        $this->assertFalse(Storage::disk('local')->exists((string) $written[0]), 'Teil-Datei nach der Rückabwicklung entfernt.');
        $this->assertSame(1, IncomingEInvoice::query()->count());
        $this->assertSame(IncomingInvoiceSplitter::PROPOSED, $container->refresh()->splitState());
    }

    /** M2: höchstens MAX_PARTS Teile; Teile zusammen höchstens das Doppelte des Originals (gemeinsame Ressourcen). */
    public function test_parts_are_limited_in_number_and_total_size(): void {
        if (! PDFSplitHelper::isPageExtractionAvailable()) {
            $this->markTestSkipped('Kein Werkzeug zum Herauslösen von Seiten.');
        }
        $tooMany = $this->stored(self::pdf(['<p>a</p>', '<p>b</p>']), ['split_proposal' => ['state' => IncomingInvoiceSplitter::PROPOSED, 'pages' => 40]]);
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $tooMany), ['starts' => array_map('strval', range(2, IncomingInvoiceSplitter::MAX_PARTS + 2))])
            ->assertSessionHasErrors('starts');
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $tooMany), ['starts' => array_map('strval', range(2, IncomingInvoiceSplitter::MAX_PARTS + 1))])
            ->assertSessionHas('error', (string) __('incoming_pdf.split.error.too_many_parts', ['max' => IncomingInvoiceSplitter::MAX_PARTS]));

        // Sechs Seiten teilen sich ein unkomprimierbares 3-MB-Bild: jeder Teil trüge es erneut.
        $image = random_bytes(1000 * 1000 * 3);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $kids = [];
        for ($i = 0; $i < 6; $i++) {
            $kids[] = (4 + 2 * $i) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count 6 >>';
        $objects[3] = "<< /Type /XObject /Subtype /Image /Width 1000 /Height 1000 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($image) . " >>\nstream\n" . $image . "\nendstream";
        $content = 'q 100 0 0 100 0 0 cm /Im0 Do Q';
        for ($i = 0; $i < 6; $i++) {
            $objects[4 + 2 * $i] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents ' . (5 + 2 * $i) . ' 0 R /Resources << /XObject << /Im0 3 0 R >> >> >>';
            $objects[5 + 2 * $i] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $pdf = "%PDF-1.7\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

        $shared = $this->stored($pdf, ['split_proposal' => ['state' => IncomingInvoiceSplitter::PROPOSED, 'pages' => 6]]);
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split', $shared), ['starts' => ['2', '3', '4', '5', '6']])
            ->assertSessionHas('error', (string) __('incoming_pdf.split.error.parts_too_large'));
        $this->assertSame(0, IncomingEInvoice::query()->whereNotNull('source_document_id')->count());
    }

    /** M3: aus dem QR gefüllte IBAN ohne vergleichbare Bankverbindung ist eine neue Bankverbindung — Sperre bis zur Bestätigung. */
    public function test_iban_from_the_code_needs_confirmation_without_known_bank_details(): void {
        $this->supplier();
        $this->scan['codes'][1] = [['type' => 'QR-Code', 'data' => self::payload(self::OTHER_IBAN)]];
        $incoming = $this->receive(self::pdf([self::invoiceHtml()]));
        $this->assertSame(self::OTHER_IBAN, $incoming->creditor_iban);
        $this->assertTrue(GiroCodeCheck::ibanFromCode($incoming));
        $this->assertContains(GiroCodeCheck::NEW_BANK_DETAILS, app(GiroCodeCheck::class)->findings($incoming));

        // „Neu aus Belegdaten“ übernimmt die QR-IBAN nicht ungeprüft in den Stamm.
        $this->assertNull(app(IncomingInvoiceParties::class)->fieldsOf($incoming)['iban']);

        $incoming->forceFill(['status' => IncomingEInvoiceStatus::PaymentReleased])->save();
        $this->assertSame('girocode_differs', app(PaymentProposalService::class)->proposalFor($incoming)['blocked']);
        $this->assertSame(0, app(PaymentRunService::class)->createFromProposals($this->account(), $this->admin, [$incoming->id])->items()->count());

        $this->confirm($incoming)->assertSessionHas('status');
        $incoming->refresh();
        $this->assertNull(app(PaymentProposalService::class)->proposalFor($incoming)['blocked']);
        $this->assertSame(self::OTHER_IBAN, app(IncomingInvoiceParties::class)->fieldsOf($incoming)['iban'], 'Nach der Bestätigung darf die IBAN vorbelegt werden.');
    }

    /** M3: nach Freigabe oder Zahlungsfreigabe füllt der GiroCode nie mehr nach. */
    public function test_code_never_fills_the_iban_after_approval(): void {
        $this->scan['codes'][1] = [['type' => 'QR-Code', 'data' => self::payload(self::OTHER_IBAN)]];
        foreach ([IncomingEInvoiceStatus::Approved, IncomingEInvoiceStatus::PaymentReleased] as $index => $status) {
            $incoming = $this->stored(self::pdf([self::invoiceHtml('MP-' . $index)]), ['status' => $status, 'girocode' => ['status' => GiroCodeReader::PENDING]]);
            app(GiroCodeReader::class)->apply($incoming);
            $this->assertNull($incoming->refresh()->creditor_iban);
            $this->assertFalse(data_get($incoming->girocode, 'filled_iban'));
        }
    }

    /** M4: die Bestätigung einer abweichenden Rechnungs-IBAN gilt nur für genau diese IBAN. */
    public function test_iban_confirmation_is_bound_to_the_confirmed_iban(): void {
        $this->supplier(self::MASTER_IBAN);
        $incoming = $this->stored(self::pdf([self::invoiceHtml()]), [
            'status' => IncomingEInvoiceStatus::PaymentReleased, 'seller_name' => 'Malerbetrieb Pinsel', 'seller_vat_id' => 'DE987654321',
            'currency' => 'EUR', 'amount_gross' => '119.00', 'creditor_iban' => self::OTHER_IBAN,
        ]);
        $proposals = app(PaymentProposalService::class);
        $this->assertSame('iban_differs', $proposals->proposalFor($incoming)['blocked']);

        $this->confirm($incoming)->assertSessionHas('status');
        $this->assertNull($proposals->proposalFor($incoming->refresh())['blocked']);

        $incoming->forceFill(['creditor_iban' => 'DE75512108001245126199'])->save();
        $this->assertSame('iban_differs', $proposals->proposalFor($incoming->refresh())['blocked'], 'Jede spätere Änderung sperrt wieder.');

        // Bestand ohne Abdruck (vor der Migration) gilt nur mit nachgerechnetem Abdruck.
        $incoming->forceFill(['creditor_iban' => self::OTHER_IBAN, 'creditor_iban_confirmed_hash' => null])->save();
        $this->assertSame('iban_differs', $proposals->proposalFor($incoming->refresh())['blocked']);
    }

    /** M5: Vorschaubilder je Organisation, Löschung der Organisation räumt sie; kein Bild für einen inzwischen verworfenen Vorschlag. */
    public function test_preview_images_are_organization_scoped_and_not_left_after_a_race(): void {
        $this->assertSame('incoming-pages/' . $this->organization->id, IncomingInvoiceSplitter::imageRoot($this->organization));
        $folders = (new \ReflectionMethod(\App\Services\Org\OrganizationLifecycleService::class, 'storageFoldersFor'))->invoke(app(\App\Services\Org\OrganizationLifecycleService::class), $this->organization);
        $this->assertContains('private/incoming-pages/' . $this->organization->id, $folders);
        $this->assertContains('private/recognition/' . $this->organization->id, $folders);

        // Während des Rasterns wählt jemand „ist eine Rechnung“: die Bilder bleiben nicht liegen.
        $this->app->instance(PagePreviewSource::class, new class implements PagePreviewSource {
            public function render(string $pdfPath, string $outputDir, int $firstPage, int $lastPage, int $dpi): array {
                $files = [];
                for ($page = $firstPage; $page <= $lastPage; $page++) {
                    $files[] = $outputDir . '/page-' . $page . '.png';
                    \CommonToolkit\Helper\FileSystem\File::write($files[array_key_last($files)], 'png');
                }
                $incoming = IncomingEInvoice::query()->latest('id')->firstOrFail();
                $incoming->forceFill(['split_proposal' => [...(array) $incoming->split_proposal, 'state' => IncomingInvoiceSplitter::DISMISSED]])->save();

                return $files;
            }
        });
        $this->app->instance(WordBoxSource::class, new class implements WordBoxSource {
            public function boxesFor(string $pdfPath): array {
                return [];
            }
        });
        $incoming = $this->stored(self::pdf(['<p>1</p>', '<p>2</p>']), ['split_proposal' => ['state' => IncomingInvoiceSplitter::PENDING, 'pages' => 2]]);
        app(IncomingInvoiceSplitter::class)->analyze($incoming);

        $this->assertSame(IncomingInvoiceSplitter::DISMISSED, $incoming->refresh()->splitState());
        $this->assertSame([], Storage::disk('local')->allFiles(IncomingInvoiceSplitter::imageRoot($this->organization) . '/' . $incoming->id . '-' . $incoming->sha256));
        $this->assertNull(app(IncomingInvoiceSplitter::class)->imagePath($incoming, 1));
    }

    /** N1: Einbehalt-Auszahlungen halten bei GiroCode-Sperre an — beim Zusammenstellen und bei der Freigabe. */
    public function test_retention_payout_respects_the_girocode_hold(): void {
        $this->supplier(self::MASTER_IBAN);
        $this->scan['codes'][1] = [['type' => 'QR-Code', 'data' => self::payload(self::OTHER_IBAN)]];
        $incoming = $this->receive(self::pdf([self::invoiceHtml('MP-4711', self::MASTER_IBAN)]));
        $incoming->forceFill(['status' => IncomingEInvoiceStatus::PaymentReleased])->save();
        $this->assertTrue(app(PaymentProposalService::class)->giroCodeUnconfirmed($incoming));
        $retention = IncomingInvoiceRetention::query()->create([
            'organization_id' => $this->organization->id, 'incoming_einvoice_id' => $incoming->id, 'kind' => 'warranty',
            'amount' => '10.00', 'currency' => 'EUR', 'status' => 'released', 'created_by' => $this->admin->id,
        ]);
        $account = $this->account();

        $run = app(PaymentRunService::class)->createFromProposals($account, $this->admin, [], null, null, [$retention->id]);
        $this->assertSame(0, $run->items()->count(), 'Zusammenstellen nimmt den Einbehalt nicht auf.');

        // Ein Lauf, der vor der Lesung entstand, gibt die Freigabe nicht frei.
        $incoming->forceFill(['girocode' => ['status' => GiroCodeReader::NONE]])->save();
        $run = app(PaymentRunService::class)->createFromProposals($account, $this->admin, [], null, null, [$retention->id]);
        $this->assertSame(1, $run->items()->count());
        $incoming->forceFill(['girocode' => GiroCodeReader::evaluate([1 => [['type' => 'QR-Code', 'data' => self::payload(self::OTHER_IBAN)]]])])->save();
        $this->expectException(RuntimeException::class);
        app(PaymentRunService::class)->release($run->refresh(), User::factory()->admin()->create(['organization_id' => $this->organization->id]));
    }

    /** N2: Bestätigung nur für die gezeigte Lage — ändert sie sich bis zum Klick, gilt sie nicht. */
    public function test_confirmation_with_a_stale_token_is_refused(): void {
        $this->supplier(self::MASTER_IBAN);
        $incoming = $this->stored(self::pdf([self::invoiceHtml()]), [
            'status' => IncomingEInvoiceStatus::PaymentReleased, 'seller_vat_id' => 'DE987654321', 'creditor_iban' => self::OTHER_IBAN,
        ]);
        $token = app(PaymentProposalService::class)->confirmationToken($incoming);
        $incoming->forceFill(['creditor_iban' => 'DE75512108001245126199'])->save();

        $this->confirm($incoming, $token)->assertSessionHas('error', (string) __('sepa.error.confirmation_stale'));
        $this->assertNull($incoming->refresh()->creditor_iban_confirmed_at);
        $this->confirm($incoming, 'kurz')->assertSessionHasErrors('token');
    }

    /** N3: Lesung und Grenzerkennung nur am geprüften Original — eine neue DMS-Version zählt nicht. */
    public function test_reading_refuses_a_replaced_original(): void {
        $this->scan['codes'][1] = [['type' => 'QR-Code', 'data' => self::payload(self::OTHER_IBAN)]];
        $incoming = $this->stored(self::pdf([self::invoiceHtml()]), ['girocode' => ['status' => GiroCodeReader::PENDING], 'split_proposal' => ['state' => IncomingInvoiceSplitter::PENDING, 'pages' => 2]]);
        $version = $incoming->document?->currentVersion;
        $this->assertNotNull($version);
        Storage::disk('local')->put((string) $version->path, self::pdf(['<p>Untergeschoben</p>', '<p>zwei</p>']));

        app(GiroCodeReader::class)->apply($incoming);
        $this->assertSame('changed', data_get($incoming->refresh()->girocode, 'reason'));
        $this->assertSame(0, $this->scan['calls']);
        $this->assertNull($incoming->creditor_iban);

        app(IncomingInvoiceSplitter::class)->analyze($incoming);
        $this->assertSame(IncomingInvoiceSplitter::FAILED, $incoming->refresh()->splitState());
    }

    /** N4: Detailseite und Zahllauf bewerten mit demselben Lieferanten — auch ohne feste Zuordnung. */
    public function test_display_and_payment_use_the_same_supplier(): void {
        $this->supplier(self::MASTER_IBAN);
        $this->scan['codes'][1] = [['type' => 'QR-Code', 'data' => self::payload(self::OTHER_IBAN)]];
        $incoming = $this->stored(self::pdf([self::invoiceHtml()]), [
            'status' => IncomingEInvoiceStatus::PaymentReleased, 'seller_vat_id' => 'DE987654321', 'girocode' => ['status' => GiroCodeReader::PENDING],
        ]);
        app(GiroCodeReader::class)->apply($incoming);
        $incoming->refresh();
        $this->assertNull($incoming->supplier_id);
        $this->assertContains(GiroCodeCheck::IBAN_DIFFERS_SUPPLIER, app(GiroCodeCheck::class)->findings($incoming), 'Ohne Zuordnung: Lieferant über die USt-IdNr. wie im Zahllauf.');

        $this->confirm($incoming)->assertSessionHas('status');
        $this->assertTrue(app(GiroCodeCheck::class)->isConfirmed($incoming->refresh()));
        $this->assertFalse(app(PaymentProposalService::class)->giroCodeUnconfirmed($incoming));
    }

    /** N8: eine hängende Aufteilung lässt sich zurücksetzen; fremde Organisation oder fehlende Person lassen keinen Dauerzustand. */
    public function test_stalled_split_can_be_reset_and_failures_never_stick(): void {
        $incoming = $this->stored(self::pdf(['<p>1</p>', '<p>2</p>']), ['split_proposal' => [
            'state' => IncomingInvoiceSplitter::SPLITTING, 'previous_state' => IncomingInvoiceSplitter::PROPOSED, 'pages' => 2,
            'requested_at' => now()->subSeconds(IncomingInvoiceSplitter::STALLED_AFTER + 60)->toIso8601String(),
        ]]);
        $this->assertTrue(IncomingInvoiceSplitter::isStalled($incoming));
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split.reset', $incoming))->assertSessionHas('success');
        $this->assertSame(IncomingInvoiceSplitter::PROPOSED, $incoming->refresh()->splitState());
        $this->assertSame(1, $incoming->auditLogs()->where('event', 'incoming_einvoice.split_reset')->count());

        // Laufende, nicht hängende Aufteilung: kein Zurücksetzen.
        $incoming->forceFill(['split_proposal' => [...(array) $incoming->split_proposal, 'state' => IncomingInvoiceSplitter::SPLITTING, 'requested_at' => now()->toIso8601String()]])->save();
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.split.reset', $incoming))->assertSessionHas('error');

        // Person einer anderen Organisation bzw. gelöschte Person: zurück auf den Vorschlag, mit Meldung.
        $stranger = User::factory()->admin()->create(['organization_id' => \App\Models\Platform\Organization::factory()->create()->id]);
        (new SplitIncomingInvoiceJob((int) $incoming->id, (int) $stranger->id, [1, 2]))->handle(app(IncomingInvoiceSplitter::class));
        $this->assertSame(IncomingInvoiceSplitter::PROPOSED, $incoming->refresh()->splitState());
        $this->assertIsString(data_get($incoming->split_proposal, 'error'));

        $incoming->forceFill(['split_proposal' => [...(array) $incoming->split_proposal, 'state' => IncomingInvoiceSplitter::SPLITTING]])->save();
        (new SplitIncomingInvoiceJob((int) $incoming->id, 999999, [1, 2]))->handle(app(IncomingInvoiceSplitter::class));
        $this->assertSame(IncomingInvoiceSplitter::PROPOSED, $incoming->refresh()->splitState());
    }

    /** N9: außerhalb der Unit-Tests immer die Medien-Verbindung — auch bei QUEUE_CONNECTION=sync. */
    public function test_jobs_always_use_the_media_connection_outside_tests(): void {
        config(['queue.default' => 'sync']);
        $environment = app()['env'];
        app()['env'] = 'production';
        try {
            $analyze = new AnalyzeIncomingInvoicePagesJob(1, 1);
            $split = new SplitIncomingInvoiceJob(1, 1, [1, 2]);
        } finally {
            app()['env'] = $environment;
        }

        $this->assertSame('media', $analyze->connection);
        $this->assertSame('media', $split->connection);
        $this->assertSame('media', $analyze->queue);
        $this->assertGreaterThan(0, $analyze->uniqueFor);
    }

    /** N10: je Organisation begrenzte Läufe je Minute — der Rest wird zurückgestellt. */
    public function test_analysis_is_rate_limited_per_organization(): void {
        $incoming = $this->stored(self::pdf([self::invoiceHtml()]), ['girocode' => ['status' => GiroCodeReader::PENDING]]);
        $key = 'incoming-pages:' . $this->organization->id;
        for ($i = 0; $i < AnalyzeIncomingInvoicePagesJob::PER_ORGANIZATION_PER_MINUTE; $i++) {
            RateLimiter::hit($key, 60);
        }

        (new AnalyzeIncomingInvoicePagesJob((int) $incoming->id, (int) $this->organization->id))
            ->handle(app(IncomingInvoiceSplitter::class), app(GiroCodeReader::class));

        $this->assertSame(GiroCodeReader::PENDING, data_get($incoming->refresh()->girocode, 'status'), 'Zurückgestellt, nicht gelesen.');
        $this->assertSame(0, $this->scan['calls']);
        RateLimiter::clear($key);
    }

    /** INFO: überlange Seitenzahl ist 404 statt 500; Steuer- und Formatzeichen im QR-Text werden entfernt. */
    public function test_page_overflow_and_format_characters_are_handled(): void {
        $incoming = $this->stored(self::pdf(['<p>1</p>', '<p>2</p>']), ['split_proposal' => ['state' => IncomingInvoiceSplitter::PROPOSED, 'pages' => 2]]);
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.split.form', $incoming) . '/seite/99999999999999999999999')->assertNotFound();
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.split.page', [$incoming, 9999]))->assertNotFound();

        $reading = GiroCodeReader::evaluate([1 => [['type' => 'QR-Code', 'data' => "BCD\n002\n1\nSCT\n\nMaler\u{202E}fdp.exe Pinsel\n" . self::MASTER_IBAN . "\nEUR119.00\n\n\nRE\u{200B}-4711"]]]);
        $this->assertSame('Malerfdp.exe Pinsel', $reading['name']);
        $this->assertSame('RE-4711', $reading['remittance']);
    }
}
