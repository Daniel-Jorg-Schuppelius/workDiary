<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingGiroCodeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Finance\BankAccount;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Billing\Sepa\{PaymentProposalService, PaymentRunService};
use App\Services\Invoicing\GiroCode\{GiroCodeCheck, GiroCodeReader};
use App\Services\Invoicing\Recognition\Contracts\QrCodeSource;
use App\Services\Invoicing\Recognition\ToolkitQrCodeSource;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use CommonToolkit\Builders\Payment\EpcQrBuilder;
use CommonToolkit\Helper\Data\DataUrlHelper;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PDFToolkit\Helper\BarcodeHelper;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * GiroCode im Rechnungseingang (Feature 208, MVP-1663) mit erfundenen
 * Rechnungen und gefälschten Scan-Ergebnissen: Lesen, IBAN übernehmen,
 * Gegenprobe, Sperre im Zahllauf bis zur auditierten Bestätigung. Echtes
 * QR-Lesen nur, wenn `zbarimg` installiert ist.
 *
 * IBANs sind die Beispiel-IBANs aus Dokumentationen, keine echten Konten.
 */
class IncomingGiroCodeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string MASTER_IBAN = 'DE89370400440532013000';

    private const string OTHER_IBAN = 'DE02120300000000202051';

    private const string SELLER_VAT = 'DE987654321';

    private User $admin;

    /** @var array{available: bool, codes: array<int, list<array{type: string, data: string}>>, calls: list<list<int>>} */
    private array $scan = ['available' => true, 'codes' => [], 'calls' => []];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE123456789']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $test = $this;
        $this->app->instance(QrCodeSource::class, new class($test) implements QrCodeSource {
            public function __construct(private readonly IncomingGiroCodeTest $test) {}

            public function isAvailable(): bool {
                return $this->test->scanState()['available'];
            }

            public function scanPdf(string $pdfPath, array $pages, int $dpi): array {
                $this->test->recordScan($pages);
                if ($pages === []) {
                    return [];
                }

                return array_intersect_key($this->test->scanState()['codes'], array_flip(range(min($pages), max($pages))));
            }

            public function scanImage(string $imagePath): array {
                return $this->test->scanState()['codes'][1] ?? [];
            }
        });
    }

    /** @return array{available: bool, codes: array<int, list<array{type: string, data: string}>>, calls: list<list<int>>} */
    public function scanState(): array {
        return $this->scan;
    }

    /** @param  list<int>  $pages */
    public function recordScan(array $pages): void {
        $this->scan['calls'][] = $pages;
    }

    /** @param  list<string>  $payloads */
    private function codesOnPage(int $page, array $payloads): void {
        $this->scan['codes'][$page] = array_map(static fn (string $data): array => ['type' => 'QR-Code', 'data' => $data], $payloads);
    }

    private static function payload(string $iban, string $amount = '119.00', string $name = 'Malerbetrieb Pinsel'): string {
        return EpcQrBuilder::to($name, $iban)->amount($amount)->remittance('MP-4711')->build();
    }

    /**
     * @param  list<string>  $pages
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $paper  Seitenmaße in Punkt
     */
    private static function pdf(array $pages, ?array $paper = null): string {
        $dompdf = new Dompdf;
        if ($paper !== null) {
            $dompdf->setPaper($paper);
        }
        $dompdf->loadHtml('<!doctype html><html lang="de"><body>' . implode('<div style="page-break-after: always"></div>', $pages) . '</body></html>');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private static function invoiceHtml(?string $iban = null, string $extra = ''): string {
        $bank = $iban === null ? '' : '<p>Bankverbindung: IBAN ' . $iban . '</p>';

        return <<<HTML
            <p>Rechnung an: Muster GmbH, USt-IdNr. DE123456789</p>
            <h1>Rechnung</h1>
            <p>Rechnungsnummer: MP-4711</p>
            <p>Rechnungsdatum: 02.11.2026</p>
            <p>Nettobetrag 100,00 EUR</p>
            <p>Umsatzsteuer 19 % 19,00 EUR</p>
            <p>Gesamtbetrag 119,00 EUR</p>
            <p>Malerbetrieb Pinsel · USt-IdNr. DE987654321</p>
            {$bank}
            {$extra}
            HTML;
    }

    private function supplier(?string $iban = null): Supplier {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Malerbetrieb Pinsel', 'vat_id' => self::SELLER_VAT]);
        if ($iban !== null) {
            $supplier->bankAccounts()->create(['organization_id' => $this->organization->id, 'account_holder' => 'Malerbetrieb Pinsel', 'iban' => $iban, 'is_primary' => true]);
        }

        return $supplier;
    }

    private function receive(string $pdf): IncomingEInvoice {
        $this->actingAs($this->admin)->post(route('finance.incoming-invoices.store'), [
            'file' => UploadedFile::fake()->createWithContent('rechnung.pdf', $pdf),
        ])->assertRedirect();

        return IncomingEInvoice::query()->latest('id')->firstOrFail();
    }

    public function test_iban_from_the_code_fills_an_empty_invoice_iban(): void {
        $this->codesOnPage(1, [self::payload(self::MASTER_IBAN)]);

        $incoming = $this->receive(self::pdf([self::invoiceHtml()]));

        $this->assertSame(GiroCodeReader::FOUND, data_get($incoming->girocode, 'status'));
        $this->assertSame(self::MASTER_IBAN, data_get($incoming->girocode, 'iban'));
        $this->assertSame('119.00', data_get($incoming->girocode, 'amount'));
        $this->assertTrue(data_get($incoming->girocode, 'filled_iban'));
        $this->assertSame(self::MASTER_IBAN, $incoming->creditor_iban);
        // Ohne Lieferanten mit Bankverbindung ist die IBAN aus dem Code eine neue Bankverbindung (Sicherheitsprüfung M3).
        $this->assertSame([GiroCodeCheck::NEW_BANK_DETAILS], app(GiroCodeCheck::class)->findings($incoming));
        $this->assertSame([[1]], $this->scan['calls'], 'Eine Seite, eine Leserunde.');
        $read = $incoming->auditLogs()->where('event', 'incoming_einvoice.girocode_read')->sole();
        $this->assertTrue($read->changes['iban_applied'] ?? null);
        $this->assertStringNotContainsString(self::MASTER_IBAN, (string) json_encode($incoming->auditLogs()->pluck('changes')->all()), 'Die IBAN steht nie im Klartext im Audit.');

        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee(__('incoming_pdf.girocode.status.found', ['page' => 1]))
            ->assertSee(__('incoming_pdf.girocode.iban_applied'));
    }

    /** Akzeptanzkriterium 3: andere IBAN als im Lieferantenstamm sperrt im Zahllauf, bis jemand bestätigt. */
    public function test_deviating_iban_warns_and_blocks_the_payment_run_until_confirmed(): void {
        $supplier = $this->supplier(self::MASTER_IBAN);
        $this->codesOnPage(1, [self::payload(self::OTHER_IBAN)]);

        $incoming = $this->receive(self::pdf([self::invoiceHtml(self::MASTER_IBAN)]));

        $this->assertSame($supplier->id, $incoming->supplier_id);
        $this->assertSame(self::MASTER_IBAN, $incoming->creditor_iban, 'Die erkannte IBAN bleibt; der Code überschreibt nichts.');
        $this->assertFalse(data_get($incoming->girocode, 'filled_iban'));
        $check = app(GiroCodeCheck::class);
        $this->assertSame([GiroCodeCheck::IBAN_DIFFERS_INVOICE, GiroCodeCheck::IBAN_DIFFERS_SUPPLIER], $check->findings($incoming));

        // Warnung im Zuordnungsdialog.
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.assign.form', $incoming))->assertOk()
            ->assertSee(__('incoming_pdf.girocode.finding.iban_differs_supplier'))
            ->assertSee(__('incoming_pdf.girocode.blocking'));

        // Sperre im Zahllauf.
        $incoming->forceFill(['status' => IncomingEInvoiceStatus::PaymentReleased])->save();
        $proposals = app(PaymentProposalService::class);
        $this->assertSame('girocode_differs', $proposals->proposalFor($incoming)['blocked']);
        $account = BankAccount::factory()->create(['organization_id' => $this->organization->id, 'iban' => 'DE75512108001245126199', 'is_active' => true]);
        $run = app(PaymentRunService::class)->createFromProposals($account, $this->admin, [$incoming->id]);
        $this->assertSame(0, $run->items()->count(), 'Gesperrte Rechnungen kommen nicht in den Lauf.');
        $this->actingAs($this->admin)->get(route('finance.payment-runs.proposals'))->assertOk()
            ->assertSee(__('sepa.blocked.girocode_differs'))->assertSee(__('sepa.action.confirm_iban'));

        // Bestätigung durch eine Person mit Freigaberecht — auditiert.
        $clerk = $this->orgUser();
        $token = ['token' => $proposals->confirmationToken($incoming)];
        $this->actingAs($clerk)->post(route('finance.payment-runs.proposals.confirm-iban', ['invoice' => $incoming]), $token)->assertForbidden();
        $this->actingAs($this->admin)->post(route('finance.payment-runs.proposals.confirm-iban', ['invoice' => $incoming]), $token)->assertRedirect();

        $incoming->refresh();
        $this->assertNotNull($incoming->girocode_confirmed_at);
        $this->assertSame($this->admin->id, $incoming->girocode_confirmed_by);
        $this->assertNull($proposals->proposalFor($incoming)['blocked']);
        $audit = $incoming->auditLogs()->where('event', 'incoming_einvoice.girocode_confirmed')->sole();
        $this->assertSame('2051', $audit->changes['girocode_iban_tail'] ?? null);
        $this->assertSame([GiroCodeCheck::IBAN_DIFFERS_INVOICE, GiroCodeCheck::IBAN_DIFFERS_SUPPLIER], $audit->changes['findings'] ?? null);

        // Die Bestätigung gilt nur für die bestätigte Lage: andere Rechnungs-IBAN ⇒ wieder gesperrt.
        $incoming->forceFill(['creditor_iban' => null])->save();
        $this->assertSame('girocode_differs', $proposals->proposalFor($incoming->refresh())['blocked']);
    }

    public function test_release_rechecks_a_code_read_after_the_run_was_built(): void {
        $this->supplier(self::MASTER_IBAN);
        $this->codesOnPage(1, []);
        $incoming = $this->receive(self::pdf([self::invoiceHtml(self::MASTER_IBAN)]));
        $incoming->forceFill(['status' => IncomingEInvoiceStatus::PaymentReleased])->save();
        $account = BankAccount::factory()->create(['organization_id' => $this->organization->id, 'iban' => 'DE75512108001245126199', 'is_active' => true]);
        $run = app(PaymentRunService::class)->createFromProposals($account, $this->admin, [$incoming->id]);
        $this->assertSame(1, $run->items()->count());

        // Der Code meldet sich erst danach (Job lief später) — die Freigabe hält an.
        $incoming->forceFill(['girocode' => GiroCodeReader::evaluate([1 => [['type' => 'QR-Code', 'data' => self::payload(self::OTHER_IBAN)]]])])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage((string) __('sepa.error.girocode_unconfirmed', ['number' => 'MP-4711']));
        app(PaymentRunService::class)->release($run->refresh(), $this->admin);
    }

    public function test_missing_reader_reports_girocode_not_readable(): void {
        $this->scan['available'] = false;

        $incoming = $this->receive(self::pdf([self::invoiceHtml()]));

        $this->assertSame(GiroCodeReader::UNREADABLE, data_get($incoming->girocode, 'status'));
        $this->assertSame('tool_missing', data_get($incoming->girocode, 'reason'));
        $this->assertSame([GiroCodeCheck::UNREADABLE], app(GiroCodeCheck::class)->findings($incoming));
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee(__('incoming_pdf.girocode.status.unreadable'))
            ->assertSee(__('incoming_pdf.girocode.reason.tool_missing'));
    }

    public function test_amount_and_name_differences_warn_without_blocking(): void {
        $this->supplier();
        $this->codesOnPage(1, [self::payload(self::MASTER_IBAN, '99.00', 'Ganz Anderer Empfaenger')]);

        $incoming = $this->receive(self::pdf([self::invoiceHtml()]));

        $findings = app(GiroCodeCheck::class)->findings($incoming);
        $this->assertSame([GiroCodeCheck::NEW_BANK_DETAILS, GiroCodeCheck::AMOUNT_DIFFERS, GiroCodeCheck::NAME_DIFFERS], $findings);
        // Betrag und Name warnen nur; die neue Bankverbindung sperrt (M3).
        $this->assertSame([GiroCodeCheck::NEW_BANK_DETAILS], array_values(array_intersect($findings, GiroCodeCheck::BLOCKING)));
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee(__('incoming_pdf.girocode.finding.amount_differs'))
            ->assertSee(__('incoming_pdf.girocode.finding.name_differs'));
    }

    public function test_wrong_check_digits_and_conflicting_codes_are_never_taken_over(): void {
        // Prüfziffer falsch: gelesen, gemeldet, nicht übernommen.
        $this->codesOnPage(1, ["BCD\n002\n1\nSCT\n\nMalerbetrieb Pinsel\nDE00370400440532013000\nEUR119.00"]);
        $invalid = $this->receive(self::pdf([self::invoiceHtml(extra: '<p>A</p>')]));
        $this->assertSame(GiroCodeReader::FOUND, data_get($invalid->girocode, 'status'));
        $this->assertFalse(data_get($invalid->girocode, 'iban_valid'));
        $this->assertNull($invalid->creditor_iban);
        $this->assertContains(GiroCodeCheck::IBAN_INVALID, app(GiroCodeCheck::class)->findings($invalid));

        // Zwei GiroCodes mit verschiedenen IBANs: widersprüchlich, sperrt, nichts übernommen.
        $this->codesOnPage(1, [self::payload(self::MASTER_IBAN), self::payload(self::OTHER_IBAN)]);
        $conflicting = $this->receive(self::pdf([self::invoiceHtml(extra: '<p>B</p>')]));
        $this->assertTrue(data_get($conflicting->girocode, 'conflicting'));
        $this->assertNull($conflicting->creditor_iban);
        $this->assertContains(GiroCodeCheck::MULTIPLE_CODES, app(GiroCodeCheck::class)->findings($conflicting));
        $this->assertTrue(app(GiroCodeCheck::class)->requiresConfirmation($conflicting));
    }

    public function test_reader_limits_pages_codes_and_payloads(): void {
        $this->codesOnPage(5, [self::payload(self::MASTER_IBAN)]);
        $incoming = $this->receive(self::pdf(array_fill(0, 5, self::invoiceHtml())));
        $this->assertSame([[1], [2], [3], [5]], $this->scan['calls'], 'Vordere Seiten und die letzte, einzeln, nie das ganze Dokument.');
        $this->assertSame(5, data_get($incoming->girocode, 'page'));

        $valid = ['type' => 'QR-Code', 'data' => self::payload(self::MASTER_IBAN)];
        $this->assertSame(GiroCodeReader::NONE, GiroCodeReader::evaluate([1 => [['type' => 'QR-Code', 'data' => 'https://example.org']]])['status']);
        $this->assertSame(GiroCodeReader::INVALID, GiroCodeReader::evaluate([1 => [['type' => 'QR-Code', 'data' => "BCD\n009\n1\nSCT"]]])['status']);
        $this->assertSame(GiroCodeReader::NONE, GiroCodeReader::evaluate([1 => [['type' => 'QR-Code', 'data' => 'BCD' . str_repeat('x', 2000)]]])['status'], 'Überlange Nutzlast wird nicht geparst.');
        $many = array_fill(0, GiroCodeReader::MAX_CODES, ['type' => 'QR-Code', 'data' => 'kein Code']);
        $this->assertSame(GiroCodeReader::NONE, GiroCodeReader::evaluate([1 => [...$many, $valid]])['status'], 'Nach MAX_CODES wird nicht weitergelesen.');
        $this->assertSame(GiroCodeReader::FOUND, GiroCodeReader::evaluate([1 => [$valid]])['status']);
    }

    /**
     * Präparierte Riesenseiten (Kante über 2000 pt) werden nicht gerastert — weder für den GiroCode noch für die
     * Vorschau. Der Eingang entsteht hier ohne Erkennung: die Texterkennung des Uploads würde solche Seiten selbst
     * per OCR lesen (Bestand, nicht Teil dieser Prüfung).
     */
    public function test_oversized_pages_are_never_rasterized(): void {
        $this->codesOnPage(1, [self::payload(self::MASTER_IBAN)]);
        $giant = [0.0, 0.0, 4000.0, 4000.0];

        $single = $this->storedIncoming(self::pdf(['<p>Rechnung</p>'], $giant));
        app(GiroCodeReader::class)->apply($single);
        $this->assertSame(GiroCodeReader::UNREADABLE, data_get($single->girocode, 'status'));
        $this->assertSame('page_size', data_get($single->girocode, 'reason'));
        $this->assertSame([], $this->scan['calls']);

        $bundle = $this->storedIncoming(self::pdf(['<p>Rechnung 1</p>', '<p>Rechnung 2</p>'], $giant));
        $bundle->forceFill(['split_proposal' => ['state' => \App\Services\Invoicing\Split\IncomingInvoiceSplitter::PENDING, 'pages' => 2]])->save();
        app(\App\Services\Invoicing\Split\IncomingInvoiceSplitter::class)->analyze($bundle);
        $this->assertSame(\App\Services\Invoicing\Split\IncomingInvoiceSplitter::TOO_LARGE, $bundle->refresh()->splitState());
    }

    /** Eingang mit abgelegtem Original, ohne Erkennung, ohne Textauszug und ohne Job. */
    private function storedIncoming(string $contents, string $name = 'riesig.pdf', string $mime = 'application/pdf'): IncomingEInvoice {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\ExtractDocumentTextJob::class]);
        $document = app(\App\Services\Document\DocumentService::class)->createFromContents(null, $this->admin, [
            'title' => 'Riesenseite', 'document_type' => \App\Enums\Document\DocumentType::Invoice->value,
        ], $contents, $name, $mime);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id,
            'document_id' => $document->id,
            'sha256' => (string) \CommonToolkit\Helper\Data\CryptoHelper::hash($contents),
            'source' => 'upload',
            'received_at' => now(),
            'status' => IncomingEInvoiceStatus::Received,
            'recognition' => \App\Enums\Invoicing\IncomingInvoiceRecognition::None,
        ]);
    }

    /** Foto einer Rechnung: direkt an den Leser; ein Bild mit erfundenen Riesenmaßen geht nie an zbarimg. */
    public function test_photo_is_scanned_directly_and_giant_images_are_refused(): void {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD fehlt.');
        }
        $this->codesOnPage(1, [self::payload(self::MASTER_IBAN)]);
        // Rauschen statt Muster: ein Foto unter 20 KB hielte der Eingang für ein Logo.
        mt_srand(208);
        $image = imagecreatetruecolor(300, 300);
        for ($x = 0; $x < 300; $x++) {
            for ($y = 0; $y < 300; $y++) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
        ob_start();
        imagepng($image);
        $photo = (string) ob_get_clean();
        $service = app(\App\Services\Invoicing\EInvoice\IncomingEInvoiceService::class);

        $this->assertGreaterThan(20_000, strlen($photo));
        $result = $service->storeMessage($this->admin, [new \App\Services\Mail\MailAttachment('foto.png', 'image/png', $photo)], new \App\Services\Invoicing\EInvoice\IncomingOrigin('lieferant@example.test', '<foto@example.test>'));
        $this->assertSame(1, $result['candidates']);
        $incoming = IncomingEInvoice::query()->latest('id')->firstOrFail();
        $this->assertSame(GiroCodeReader::FOUND, data_get($incoming->girocode, 'status'));
        $this->assertSame([], $this->scan['calls'], 'Ein Bild wird nicht als PDF gerastert.');

        // PNG-Kopf mit 50.000 × 50.000 Pixeln (nur Kopf, kein Bildinhalt).
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        $giant = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 50000, 50000, 8, 2, 0, 0, 0))
            . $chunk('tEXt', "Comment\0" . str_repeat('x', 25000)) . $chunk('IEND', '');
        // Ohne Erkennung abgelegt: OCR des Uploads läse das Bild sonst selbst (Bestand, nicht Teil dieser Prüfung).
        $refused = $this->storedIncoming($giant, 'riesig.png', 'image/png');
        app(GiroCodeReader::class)->apply($refused);
        $this->assertSame(GiroCodeReader::UNREADABLE, data_get($refused->girocode, 'status'));
        $this->assertSame('too_large', data_get($refused->girocode, 'reason'));
    }

    /** Gelesen wird in der Medien-Warteschlange; bis dahin zeigt der Eingang „wird gelesen“, der Bestand ohne Lesung gar nichts. */
    public function test_reading_runs_on_the_media_queue_and_shows_as_pending(): void {
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\Invoicing\AnalyzeIncomingInvoicePagesJob::class]);

        $incoming = $this->receive(self::pdf([self::invoiceHtml()]));

        \Illuminate\Support\Facades\Bus::assertDispatched(
            \App\Jobs\Invoicing\AnalyzeIncomingInvoicePagesJob::class,
            static fn (\App\Jobs\Invoicing\AnalyzeIncomingInvoicePagesJob $job): bool => $job->incomingId === $incoming->id && $job->queue === 'media',
        );
        $this->assertSame([], $this->scan['calls'], 'Kein Rastern im Request.');
        $this->assertSame(GiroCodeReader::PENDING, data_get($incoming->girocode, 'status'));
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertSee(__('incoming_pdf.girocode.pending'));

        $incoming->forceFill(['girocode' => null])->save();
        $this->actingAs($this->admin)->get(route('finance.incoming-invoices.show', $incoming->document))->assertOk()
            ->assertDontSee(__('incoming_pdf.girocode.pending'));
    }

    /** Echtes QR-Lesen mit zbarimg: nur, wenn das Programm installiert ist (Paket zbar-tools). */
    public function test_real_qr_code_is_read_with_zbarimg(): void {
        $this->app->forgetInstance(QrCodeSource::class);
        $this->app->bind(QrCodeSource::class, ToolkitQrCodeSource::class);
        if (! BarcodeHelper::isAvailable()) {
            $this->markTestSkipped('zbarimg (zbar-tools) ist nicht installiert.');
        }
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd)))->writeString(self::payload(self::MASTER_IBAN));
        $pdf = self::pdf([self::invoiceHtml(extra: '<img src="' . DataUrlHelper::encode($svg, 'image/svg+xml') . '" width="180" height="180">')]);

        $incoming = $this->receive($pdf);

        $this->assertSame(GiroCodeReader::FOUND, data_get($incoming->girocode, 'status'));
        $this->assertSame(self::MASTER_IBAN, data_get($incoming->girocode, 'iban'));
        $this->assertSame('119.00', data_get($incoming->girocode, 'amount'));
    }
}
