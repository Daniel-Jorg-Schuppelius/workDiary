<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceSplitter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Split;

use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition, IncomingInvoiceTransferStatus};
use App\Jobs\Invoicing\{AnalyzeIncomingInvoicePagesJob, SplitIncomingInvoiceJob, TransferIncomingInvoiceJob};
use App\Models\Accounting\{AccountingOpenItem, FixedAsset};
use App\Models\AssetFinance\AssetFinanceRateSchedule;
use App\Models\Claims\ClaimSupplierRecourse;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, SelfBillingCredit};
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\EInvoice\{IncomingEInvoiceService, IncomingInvoiceTransferService};
use App\Services\Invoicing\Recognition\Contracts\{PagePreviewSource, WordBoxSource};
use App\Services\Licensing\LimitGuard;
use App\Services\Retention\LegalHoldService;
use App\Support\ErrorText;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{BankHelper, CryptoHelper, VatNumberHelper};
use CommonToolkit\Helper\FileSystem\{File, Folder};
use ERRORToolkit\Exceptions\FileSystem\FileNotWrittenException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\{Cache, DB, Log, Storage};
use PDFToolkit\Helper\{PDFHelper, PDFSplitHelper};
use RuntimeException;
use Throwable;

/**
 * Sammel-PDF im Rechnungseingang (Feature 208, MVP-1662).
 *
 * Eine mehrseitige PDF ohne E-Rechnungsdaten bekommt beim Eingang den Stand
 * „Prüfung läuft“; ein Job der Medien-Warteschlange schlägt Rechnungsgrenzen
 * vor ({@see InvoiceBoundaryDetector}) und rastert Vorschaubilder. Solange die
 * Prüfung läuft oder ein Vorschlag mit mehreren Rechnungen offen ist, warten
 * Übergabe an die Buchhaltung und Zahlung.
 *
 * Bestätigt jemand die Grenzen, entsteht je Rechnung ein eigener Eingang mit
 * einer Teil-PDF ({@see PDFSplitHelper::extractPages()}) als abgeleitetem
 * Dokument, das auf Original und Seitenbereich verweist. Der Sammel-Eingang
 * bleibt als Nachweis mit dem Status „aufgeteilt“ erhalten (GoBD), steht aber
 * in keiner Arbeitsliste und ist weder zahl- noch übergabefähig. Das Original
 * bleibt Byte für Byte im DMS (Hash geprüft vor jeder Seitenarbeit), zeigt
 * seine Teile und lässt sich nicht löschen, solange ein Teil besteht
 * ({@see \App\Services\Document\DocumentService}). Jeder Teil durchläuft die
 * Dublettenprüfung des Eingangs: Hash des Teils, Rechnungsnummer und Absender.
 *
 * Grenzen gegen präparierte Dateien: höchstens {@see MAX_PAGES} Seiten,
 * {@see MAX_BYTES} und {@see MAX_PARTS} Teile; die Teile zusammen höchstens
 * {@see PARTS_SIZE_FACTOR}-mal so groß wie das Original (mindestens
 * {@see PARTS_SIZE_FLOOR}) und im Speicherkontingent; keine Seite über
 * {@see MAX_PAGE_POINTS}; Vorschaubilder mit {@see IMAGE_DPI}. Werkzeuge und
 * Erkennung (OCR, KI-Rückfall) laufen nur im Job und nie innerhalb einer
 * Datenbanktransaktion.
 */
final class IncomingInvoiceSplitter {
    public const int MAX_PAGES = 60;

    public const int MAX_BYTES = 50 * 1024 * 1024;

    /** Höchstzahl Rechnungen je Sammel-PDF. */
    public const int MAX_PARTS = 30;

    /** Die Teil-PDFs zusammen dürfen das Original höchstens um diesen Faktor übersteigen … */
    public const int PARTS_SIZE_FACTOR = 2;

    /** … mindestens aber so groß sein (kleine Dateien wiederholen eingebettete Schriften je Teil). */
    public const int PARTS_SIZE_FLOOR = 5 * 1024 * 1024;

    public const int IMAGE_DPI = 50;

    /**
     * Längste Seitenkante in Punkt (≈ 70 cm). Größere Seiten werden weder
     * gerastert noch per OCR gelesen: eine präparierte Seite mit 200 Zoll
     * Kantenlänge ergäbe Bilder mit Hunderten Millionen Pixeln.
     */
    public const int MAX_PAGE_POINTS = 2000;

    /** Laufzeitgrenze der Jobs; die Sperre beim Teilen hält länger. */
    public const int JOB_TIMEOUT = 1800;

    private const int LOCK_SECONDS = self::JOB_TIMEOUT + 600;

    /** Läuft eine Aufteilung länger, darf jemand sie zurücksetzen. */
    public const int STALLED_AFTER = 2 * 3600;

    /** Grenzerkennung wartet auf den Job. */
    public const string PENDING = 'pending';

    /** Mehrere Rechnungen vorgeschlagen, noch nicht entschieden. */
    public const string PROPOSED = 'proposed';

    /** Eine Rechnung erkannt; von Hand teilbar. */
    public const string SINGLE = 'single';

    /** Von Hand als eine Rechnung bestätigt. */
    public const string DISMISSED = 'dismissed';

    /** Aufteilung bestätigt, der Job arbeitet. */
    public const string SPLITTING = 'splitting';

    /** Aufgeteilt; der Sammel-Eingang bleibt als Nachweis (Status {@see IncomingEInvoiceStatus::Split}). */
    public const string SPLIT = 'split';

    /** Über den Grenzen — keine automatische Prüfung. */
    public const string TOO_LARGE = 'too_large';

    /** Prüfung gescheitert; gilt als eine Rechnung. */
    public const string FAILED = 'failed';

    /** @var list<string> Zustände, in denen Übergabe und Zahlung warten */
    public const array HOLDING = [self::PENDING, self::PROPOSED, self::SPLITTING];

    /** @var list<string> Zustände, aus denen sich aufteilen lässt */
    private const array SPLITTABLE = [self::PROPOSED, self::SINGLE, self::DISMISSED];

    /** @var list<string> Zustände mit Vorschaubildern */
    private const array WITH_IMAGES = [self::PROPOSED, self::SINGLE];

    private const string IMAGE_DIRECTORY = 'incoming-pages/';

    private const string LOCK_PREFIX = 'incoming-split:';

    public function __construct(
        private readonly WordBoxSource $boxes,
        private readonly PagePreviewSource $images,
        private readonly InvoiceBoundaryDetector $detector,
    ) {}

    /**
     * Stand beim Eingang: eine mehrseitige PDF ohne E-Rechnungsdaten wartet
     * auf die Grenzerkennung, alles andere hat keinen Stand. Nur `pdfinfo` —
     * die eigentliche Arbeit macht der Job.
     *
     * @return array<string, mixed>|null
     */
    public static function initialState(string $contents, IncomingInvoiceRecognition $recognition): ?array {
        if ($recognition === IncomingInvoiceRecognition::Structured || File::mimeTypeFromContent($contents) !== 'application/pdf') {
            return null;
        }
        try {
            $pages = (int) File::withTemp($contents, static fn (string $path): int => PDFHelper::getPageCount($path), 'split-probe-', 'pdf');
        } catch (FileNotWrittenException) {
            return null;
        }
        if ($pages < 2) {
            return null;
        }

        return [
            'state' => $pages > self::MAX_PAGES || strlen($contents) > self::MAX_BYTES ? self::TOO_LARGE : self::PENDING,
            'pages' => $pages,
        ];
    }

    /**
     * Keine Seite über {@see MAX_PAGE_POINTS} — gemessen an der größeren von
     * MediaBox und CropBox (pdftoppm rastert ohne `-cropbox` die MediaBox,
     * pdfinfo nennt sonst nur die CropBox; Sicherheitsprüfung H1). Sind die
     * Seitenmaße nicht lesbar, wird nicht gerastert.
     */
    public static function pagesWithinLimits(string $pdfPath, int $firstPage = 1, int $lastPage = 0): bool {
        $boxes = PDFHelper::getAllPageBoxes($pdfPath, $firstPage, $lastPage);
        if ($boxes === []) {
            return false;
        }
        foreach ($boxes as $box) {
            $largest = $box->largest();
            if (max($largest->widthPt, $largest->heightPt) > self::MAX_PAGE_POINTS) {
                return false;
            }
        }

        return true;
    }

    /** Übergabe und Zahlung warten auf die Entscheidung über die Grenzen. */
    public static function isHolding(IncomingEInvoice $incoming): bool {
        return in_array($incoming->splitState(), self::HOLDING, true);
    }

    /** Seitenzahl laut Grenzerkennung; 0 ohne Stand. */
    public static function pageCount(IncomingEInvoice $incoming): int {
        return max(0, (int) (((array) $incoming->split_proposal)['pages'] ?? 0));
    }

    /** Aufteilung hängt (Worker abgestürzt, Sperre verloren): jemand darf sie zurücksetzen. */
    public static function isStalled(IncomingEInvoice $incoming): bool {
        if ($incoming->splitState() !== self::SPLITTING) {
            return false;
        }
        $requested = ((array) $incoming->split_proposal)['requested_at'] ?? null;
        if (! is_string($requested) || $requested === '') {
            return true;
        }

        return CarbonImmutable::parse($requested)->addSeconds(self::STALLED_AFTER)->isPast();
    }

    /**
     * Grenzen vorschlagen und Vorschaubilder rastern (im Job). Nur aus dem
     * Stand „läuft“ und nur am unveränderten Original; die Bilder entstehen
     * erst, nachdem der Vorschlag unter Sperre gespeichert ist — wer
     * inzwischen „ist eine Rechnung“ gewählt hat, bekommt keine.
     */
    public function analyze(IncomingEInvoice $incoming): void {
        if ($incoming->splitState() !== self::PENDING) {
            return;
        }
        $original = $incoming->verifiedOriginal();
        if ($original === null || strlen($original['contents']) > self::MAX_BYTES) {
            $this->settle($incoming, [
                'state' => $original === null ? self::FAILED : self::TOO_LARGE,
                'pages' => self::pageCount($incoming),
                'error' => $original === null ? (string) __('incoming_pdf.split.error.original_changed') : null,
            ]);

            return;
        }

        File::withTemp($original['contents'], function (string $pdf) use ($incoming): void {
            $pages = PDFHelper::getPageCount($pdf);
            if ($pages < 2) {
                $this->settle($incoming, ['state' => self::SINGLE, 'pages' => max(1, $pages), 'starts' => [1], 'images' => false]);

                return;
            }
            if ($pages > self::MAX_PAGES || ! self::pagesWithinLimits($pdf)) {
                $this->settle($incoming, ['state' => self::TOO_LARGE, 'pages' => $pages]);

                return;
            }
            ['texts' => $texts, 'headers' => $headers] = InvoiceBoundaryDetector::pagesFromBoxes($this->boxes->boxesFor($pdf), $pages);
            $result = $this->detector->propose($texts, $headers, $this->ownIdentifiers($incoming));
            $signals = [];
            foreach ($result['signals'] as $page => $reasons) {
                $signals[(string) $page] = $reasons;
            }
            $stored = $this->settle($incoming, [
                'state' => count($result['starts']) > 1 ? self::PROPOSED : self::SINGLE,
                'pages' => $pages,
                'starts' => $result['starts'],
                'signals' => $signals,
                'images' => false,
            ]);
            if (! $stored) {
                return;
            }

            $rendered = $this->renderImages($incoming, $pdf, $pages);
            DB::transaction(function () use ($incoming, $rendered): void {
                $this->lockFresh($incoming);
                if (in_array($incoming->splitState(), self::WITH_IMAGES, true)) {
                    $this->store($incoming, [...(array) $incoming->split_proposal, 'images' => $rendered]);
                } else {
                    $this->forgetImages($incoming);
                }
            });
        }, 'split-', 'pdf');
    }

    /** Prüfung im Job gescheitert: gilt als eine Rechnung, damit Übergabe und Zahlung nicht ewig warten. */
    public function markFailed(IncomingEInvoice $incoming): void {
        if ($incoming->splitState() === self::PENDING) {
            $this->store($incoming, [...(array) $incoming->split_proposal, 'state' => self::FAILED]);
        }
    }

    /**
     * Warum sich der Eingang (noch) nicht aufteilen lässt — Schlüssel unter
     * `incoming_pdf.split.blocked.*`; null = aufteilbar.
     */
    public function blocker(IncomingEInvoice $incoming): ?string {
        $state = $incoming->splitState();

        return match (true) {
            $incoming->isSplitPart() => 'part',
            $incoming->isSplitBundle() || $state === self::SPLIT => 'split',
            $state === null || $incoming->recognition === IncomingInvoiceRecognition::Structured => 'no_bundle',
            $state === self::PENDING => 'pending',
            $state === self::SPLITTING => 'running',
            $state === self::TOO_LARGE => 'too_large',
            ! in_array($state, self::SPLITTABLE, true) => 'no_bundle',
            ! in_array($incoming->status, [IncomingEInvoiceStatus::Received, IncomingEInvoiceStatus::Question], true) => 'status',
            $incoming->transferred_at !== null || $incoming->transfers()->whereIn('status', [IncomingInvoiceTransferStatus::Transferred->value, IncomingInvoiceTransferStatus::Linked->value])->exists() => 'transferred',
            $incoming->paid_in_run_id !== null => 'payment',
            $incoming->retentions()->exists() || $this->isLinked($incoming) => 'linked',
            app(LegalHoldService::class)->activeHoldFor($incoming) !== null => 'hold',
            default => null,
        };
    }

    /**
     * Startseiten prüfen: ganze Zahlen im Dokument, Seite 1 immer dabei,
     * aufsteigend, ohne Doppel, höchstens {@see MAX_PARTS}.
     *
     * @param  array<mixed>  $starts
     * @return list<int>
     */
    public static function normalizeStarts(array $starts, int $pages): array {
        $valid = [1];
        foreach ($starts as $start) {
            $page = is_int($start) || (is_string($start) && ctype_digit($start) && strlen($start) <= 4) ? (int) $start : 0;
            if ($page < 1 || $page > $pages) {
                throw new RuntimeException((string) __('incoming_pdf.split.error.invalid_page'));
            }
            $valid[] = $page;
        }
        $valid = array_values(array_unique($valid));
        sort($valid);
        if (count($valid) > self::MAX_PARTS) {
            throw new RuntimeException((string) __('incoming_pdf.split.error.too_many_parts', ['max' => self::MAX_PARTS]));
        }

        return $valid;
    }

    /**
     * Aufteilung bestätigen: Stand „läuft“ unter Zeilensperre (kein doppeltes
     * Absenden), dann der Job. Liefert die geprüften Startseiten.
     *
     * @param  array<mixed>  $starts
     * @return list<int>
     */
    public function request(IncomingEInvoice $incoming, array $starts, User $actor): array {
        $normalized = DB::transaction(function () use ($incoming, $starts, $actor): array {
            $this->lockFresh($incoming);
            $reason = $this->blocker($incoming);
            if ($reason !== null) {
                throw new RuntimeException((string) __('incoming_pdf.split.blocked.' . $reason));
            }
            $normalized = self::normalizeStarts($starts, self::pageCount($incoming));
            if (count($normalized) < 2) {
                throw new RuntimeException((string) __('incoming_pdf.split.error.single'));
            }
            $proposal = (array) $incoming->split_proposal;
            $this->store($incoming, [
                ...$proposal,
                'state' => self::SPLITTING,
                'previous_state' => $proposal['state'] ?? self::PROPOSED,
                'requested_starts' => $normalized,
                'requested_by' => $actor->id,
                'requested_at' => now()->toIso8601String(),
                'error' => null,
            ]);

            return $normalized;
        });

        SplitIncomingInvoiceJob::dispatch((int) $incoming->id, (int) $actor->id, $normalized)->afterCommit();

        return $normalized;
    }

    /**
     * Teilen (im Job). Erwartete Fehler (Werkzeug fehlt, Original verändert,
     * Grenzen, Kontingent) landen als Meldung am Stand, der Eingang bleibt
     * unverändert. Hält ein anderer Lauf die Sperre, arbeitet der; hängt er,
     * setzt {@see resetStalled()} zurück.
     *
     * @param  list<int>  $starts
     * @return array{created: list<IncomingEInvoice>, duplicates: int, discarded: int}|null
     */
    public function split(IncomingEInvoice $container, array $starts, User $actor): ?array {
        $lock = Cache::lock(self::LOCK_PREFIX . $container->id, self::LOCK_SECONDS);
        if (! $lock->get()) {
            return null;
        }
        try {
            $container->refresh();
            if ($container->splitState() !== self::SPLITTING) {
                return null;
            }
            if ((int) $actor->organization_id !== (int) $container->organization_id) {
                throw new RuntimeException((string) __('incoming_pdf.split.error.failed'));
            }

            return $this->splitLocked($container, $starts, $actor);
        } catch (RuntimeException $e) {
            Log::warning('Sammel-PDF nicht aufgeteilt', ['incoming_id' => $container->id, 'error' => $e->getMessage()]);
            // Nur eigene Meldungen erreichen den Menschen; Fremdtexte (Pfade, Werkzeugausgaben) bleiben im Protokoll.
            $this->abandon($container, ErrorText::for($e));

            return null;
        } finally {
            $lock->release();
        }
    }

    /** Aufteilung gescheitert: zurück auf den vorigen Stand, mit Meldung. */
    public function abandon(IncomingEInvoice $container, string $error): void {
        if (! $container->exists) {
            return;
        }
        DB::transaction(function () use ($container, $error): void {
            $this->lockFresh($container);
            if ($container->splitState() !== self::SPLITTING) {
                return;
            }
            $proposal = (array) $container->split_proposal;
            $this->store($container, [...$proposal, 'state' => (string) ($proposal['previous_state'] ?? self::PROPOSED), 'error' => mb_substr($error, 0, 500)]);
        });
    }

    /** Ausweg für eine hängende Aufteilung (N8): zurück auf den Vorschlag, auditiert. */
    public function resetStalled(IncomingEInvoice $container, User $actor): void {
        if (! self::isStalled($container)) {
            throw new RuntimeException((string) __('incoming_pdf.split.blocked.running'));
        }
        $this->abandon($container, (string) __('incoming_pdf.split.error.stalled'));
        $container->audit('incoming_einvoice.split_reset', ['actor_user_id' => $actor->id]);
    }

    /**
     * „Ist eine Rechnung“: Vorschlag verwerfen. Danach liest der Job den
     * GiroCode, und eine wartende Übergabe läuft an.
     */
    public function dismiss(IncomingEInvoice $incoming, User $actor): void {
        DB::transaction(function () use ($incoming, $actor): void {
            $this->lockFresh($incoming);
            // Auch während der Prüfung: der Ausweg, wenn der Medien-Job (noch) nicht läuft.
            if ($incoming->isSplitPart() || ! in_array($incoming->splitState(), [self::PENDING, self::PROPOSED, self::SINGLE], true)) {
                throw new RuntimeException((string) __('incoming_pdf.split.blocked.no_bundle'));
            }
            $this->store($incoming, [...(array) $incoming->split_proposal, 'state' => self::DISMISSED, 'images' => false, 'dismissed_by' => $actor->id, 'dismissed_at' => now()->toIso8601String()]);
            $incoming->audit('incoming_einvoice.split_dismissed', ['pages' => self::pageCount($incoming)]);
        });
        $this->forgetImages($incoming);
        AnalyzeIncomingInvoicePagesJob::dispatch((int) $incoming->id, (int) $incoming->organization_id)->afterCommit();
        $this->releaseTransfer($incoming);
    }

    /** Wartende Übergabe nach dem Ende der Sammel-PDF-Prüfung anstoßen. */
    public function releaseTransfer(IncomingEInvoice $incoming): void {
        $organization = $incoming->organization;
        if ($organization !== null && $incoming->counterparty() !== null && app(IncomingInvoiceTransferService::class)->hasTargets($organization)) {
            TransferIncomingInvoiceJob::dispatch((int) $incoming->id);
        }
    }

    /** Absoluter Pfad des Vorschaubilds; null ohne Bild oder außerhalb des Dokuments. */
    public function imagePath(IncomingEInvoice $incoming, int $page): ?string {
        if ($page < 1 || $page > self::pageCount($incoming) || ! in_array($incoming->splitState(), self::WITH_IMAGES, true)) {
            return null;
        }
        $relative = self::imageDirectory($incoming) . '/page-' . $page . '.png';
        $disk = Storage::disk('local');

        return $disk->exists($relative) ? $disk->path($relative) : null;
    }

    /**
     * Teile eines Originals in Seitenreihenfolge.
     *
     * @return Collection<int, IncomingEInvoice>
     */
    public static function partsOf(Document $original): Collection {
        return IncomingEInvoice::query()->withoutGlobalScopes()
            ->where('organization_id', $original->organization_id)
            ->where('source_document_id', $original->id)
            ->with('document')
            ->orderBy('source_page_from')
            ->get();
    }

    /** Vorschaubilder entfernen (nach dem Teilen, „ist eine Rechnung“, beim Löschen des Eingangs). */
    public function forgetImages(IncomingEInvoice $incoming): void {
        Storage::disk('local')->deleteDirectory(self::imageDirectory($incoming));
    }

    /** Ablage der Vorschaubilder einer Organisation — Löschung der Organisation räumt den ganzen Ordner. */
    public static function imageRoot(Organization|int $organization): string {
        return self::IMAGE_DIRECTORY . (int) ($organization instanceof Organization ? $organization->id : $organization);
    }

    /**
     * @param  list<int>  $starts
     * @return array{created: list<IncomingEInvoice>, duplicates: int, discarded: int}
     */
    private function splitLocked(IncomingEInvoice $container, array $starts, User $actor): array {
        $document = $container->document;
        if ($document === null || $container->storedOriginal() === null) {
            throw new RuntimeException((string) __('incoming_pdf.split.error.original_missing'));
        }
        // Das Original bleibt unverändert — sonst wäre der Verweis der Teile kein Nachweis.
        $original = $container->verifiedOriginal();
        if ($original === null) {
            throw new RuntimeException((string) __('incoming_pdf.split.error.original_changed'));
        }
        if (! PDFSplitHelper::isPageExtractionAvailable()) {
            throw new RuntimeException((string) __('incoming_pdf.split.error.tool_missing'));
        }
        $baseName = File::sanitizeFilename(File::filename($original['name'], false)) ?: 'sammel-pdf';
        $service = app(IncomingEInvoiceService::class);
        $organizationId = (int) $container->organization_id;

        return Folder::withTemp(function (string $directory) use ($container, $document, $original, $starts, $actor, $baseName, $service, $organizationId): array {
            $source = $directory . DIRECTORY_SEPARATOR . 'original.pdf';
            File::write($source, $original['contents']);
            $pages = PDFHelper::getPageCount($source);
            $starts = self::normalizeStarts($starts, $pages);
            if ($pages < 2 || $pages > self::MAX_PAGES || count($starts) < 2) {
                throw new RuntimeException((string) __('incoming_pdf.split.error.invalid_page'));
            }
            // Auch nach „ist eine Rechnung“ ohne Prüfung: jeder Teil wird erkannt (OCR) — nie mit Riesenseiten.
            if (! self::pagesWithinLimits($source)) {
                throw new RuntimeException((string) __('incoming_pdf.split.blocked.too_large'));
            }

            // 1. Teile herauslösen und messen, bevor irgendetwas erkannt wird: gemeinsame Ressourcen
            //    (Bilder, Schriften) wiederholen sich je Teil — die Summe ist begrenzt.
            $budget = max(self::PARTS_SIZE_FACTOR * strlen($original['contents']), self::PARTS_SIZE_FLOOR);
            $total = 0;
            $prepared = [];
            foreach ($starts as $index => $from) {
                $to = ($starts[$index + 1] ?? ($pages + 1)) - 1;
                $target = $directory . DIRECTORY_SEPARATOR . 'part-' . ($index + 1) . '.pdf';
                if (! PDFSplitHelper::extractPages($source, $target, $from, $to) || ! File::isFile($target)) {
                    throw new RuntimeException((string) __('incoming_pdf.split.error.extract_failed', ['from' => $from, 'to' => $to]));
                }
                $total += File::size($target);
                if ($total > $budget) {
                    throw new RuntimeException((string) __('incoming_pdf.split.error.parts_too_large'));
                }
                $prepared[] = ['from' => $from, 'to' => $to, 'path' => $target, 'name' => sprintf('%s-S%d-%d.pdf', $baseName, $from, $to), 'summary' => []];
            }
            // Wie beim Upload: die Teile zählen gegen das Speicherkontingent der Organisation.
            $organization = Organization::query()->withoutGlobalScopes()->findOrFail($organizationId);
            app(LimitGuard::class)->ensureCanStoreAttachment($organization, $total, $actor);

            // 2. Je Teil erkennen (OCR, KI-Rückfall) — ohne Transaktion, nur eine Teil-Datei im Speicher.
            foreach ($prepared as $index => $part) {
                $contents = File::read($part['path']);
                if (! $service->isKnownFile($organizationId, (string) CryptoHelper::hash($contents))) {
                    $prepared[$index]['summary'] = $service->analyzePart($actor, $contents, $part['name']);
                }
                unset($contents);
            }

            // 3. Kurz persistieren. Bei Rückabwicklung die schon geschriebenen Teil-Dateien wieder entfernen.
            $written = [];
            try {
                $result = DB::transaction(function () use ($container, $document, $prepared, $actor, $service, $baseName, &$written): array {
                    $this->lockFresh($container);
                    if ($container->splitState() !== self::SPLITTING || ! $container->status->canTransitionTo(IncomingEInvoiceStatus::Split)) {
                        throw new RuntimeException((string) __('incoming_pdf.split.error.failed'));
                    }
                    // Der Sammel-Eingang trägt die Nummer der ersten Rechnung; ohne Schlüssel hält ihn die Dublettenprüfung der Teile nicht für ein Doppel.
                    $container->newQueryWithoutScopes()->whereKey($container->getKey())->update(['invoice_number_key' => null]);

                    $created = [];
                    $duplicates = 0;
                    $discarded = 0;
                    foreach ($prepared as $part) {
                        $stored = $service->storePart($actor, $container, File::read($part['path']), $part['summary'], $part['name'], $part['from'], $part['to']);
                        if ($stored['status'] === 'created' && $stored['incoming'] instanceof IncomingEInvoice) {
                            $created[] = $stored['incoming'];
                            $path = $stored['incoming']->document?->currentVersion?->path;
                            if (is_string($path) && $path !== '') {
                                $written[] = $path;
                            }
                        } elseif ($stored['status'] === 'duplicate') {
                            $duplicates++;
                        } else {
                            $discarded++;
                        }
                    }
                    if ($created === []) {
                        throw new RuntimeException((string) __('incoming_pdf.split.error.nothing_created'));
                    }

                    // Der Sammel-Eingang bleibt als Nachweis (GoBD): Status „aufgeteilt“, Verweis auf seine Teile.
                    $parts = array_map(static fn (IncomingEInvoice $part): array => ['incoming_id' => $part->id, 'from' => $part->source_page_from, 'to' => $part->source_page_to], $created);
                    $container->forceFill([
                        'status' => IncomingEInvoiceStatus::Split,
                        'split_proposal' => [...(array) $container->split_proposal, 'state' => self::SPLIT, 'parts' => $parts, 'split_at' => now()->toIso8601String(), 'split_by' => $actor->id, 'images' => false, 'error' => null],
                    ])->save();
                    $container->audit('incoming_einvoice.split', [
                        'sha256' => $container->sha256,
                        'parts' => $parts,
                        'duplicates' => $duplicates,
                        'actor_user_id' => $actor->id,
                    ]);

                    $document->forceFill([
                        'title' => (string) __('incoming_pdf.split.document_title', ['file' => $baseName, 'count' => count($prepared)]),
                        'description' => (string) __('incoming_pdf.split.document_description', ['count' => count($prepared)]),
                    ])->save();
                    $document->audit('document.invoice_split', [
                        'sha256' => $container->sha256,
                        'parts' => $parts,
                        'duplicates' => $duplicates,
                        'actor_user_id' => $actor->id,
                    ]);

                    return ['created' => $created, 'duplicates' => $duplicates, 'discarded' => $discarded];
                });
            } catch (Throwable $e) {
                foreach ($written as $path) {
                    Storage::disk('local')->delete($path);
                }

                throw $e;
            }

            $this->forgetImages($container);

            return $result;
        }, 'split-');
    }

    /**
     * Stand unter Zeilensperre speichern, nur solange die Prüfung noch läuft.
     *
     * @param  array<string, mixed>  $proposal
     */
    private function settle(IncomingEInvoice $incoming, array $proposal): bool {
        return DB::transaction(function () use ($incoming, $proposal): bool {
            $this->lockFresh($incoming);
            // Hat jemand inzwischen „ist eine Rechnung“ gewählt, bleibt es dabei.
            if ($incoming->splitState() !== self::PENDING) {
                return false;
            }
            $this->store($incoming, [...(array) $incoming->split_proposal, ...$proposal, 'analyzed_at' => now()->toIso8601String()]);

            return true;
        });
    }

    private function lockFresh(IncomingEInvoice $incoming): void {
        $incoming->newQueryWithoutScopes()->whereKey($incoming->getKey())->lockForUpdate()->first();
        $incoming->refresh();
    }

    /** Vorschaubilder aller Seiten (eine Werkzeugrunde, niedrige Auflösung); true, wenn alle da sind. */
    private function renderImages(IncomingEInvoice $incoming, string $pdf, int $pages): bool {
        $disk = Storage::disk('local');
        $directory = self::imageDirectory($incoming);
        $disk->deleteDirectory($directory);
        $disk->makeDirectory($directory);
        $rendered = $this->images->render($pdf, $disk->path($directory), 1, $pages, self::IMAGE_DPI);
        if (count($rendered) !== $pages) {
            $disk->deleteDirectory($directory);

            return false;
        }
        foreach ($rendered as $index => $file) {
            $target = $disk->path($directory . '/page-' . ($index + 1) . '.png');
            if ($file !== $target && File::isFile($file)) {
                File::move($file, dirname($target), basename($target));
            }
        }

        return true;
    }

    /** Je Organisation und Eingang: dieselbe Datei kann in zwei Organisationen liegen. */
    private static function imageDirectory(IncomingEInvoice $incoming): string {
        return self::imageRoot((int) $incoming->organization_id) . '/' . (int) $incoming->id . '-' . $incoming->sha256;
    }

    /** @param  array<string, mixed>  $proposal */
    private function store(IncomingEInvoice $incoming, array $proposal): void {
        $incoming->forceFill(['split_proposal' => $proposal])->save();
    }

    /**
     * Eigene USt-IdNr. und IBANs zählen nie als neuer Absender.
     *
     * @return list<string>
     */
    private function ownIdentifiers(IncomingEInvoice $incoming): array {
        $own = app(IncomingEInvoiceService::class)->ownIdentity($incoming->organization);

        return array_values(array_filter([
            $own['vat'] !== '' ? VatNumberHelper::normalize($own['vat']) : null,
            ...array_map(static fn (string $iban): ?string => BankHelper::normalizeIBAN($iban), $own['ibans']),
        ]));
    }

    /** Verknüpfungen anderer Bereiche, die beim Teilen ins Leere zeigten. */
    private function isLinked(IncomingEInvoice $incoming): bool {
        $morph = $incoming->getMorphClass();

        return ClaimSupplierRecourse::query()->withoutGlobalScopes()->where('incoming_einvoice_id', $incoming->id)->exists()
            || AssetFinanceRateSchedule::query()->withoutGlobalScopes()->where('incoming_einvoice_id', $incoming->id)->exists()
            || SelfBillingCredit::query()->withoutGlobalScopes()->where('incoming_e_invoice_id', $incoming->id)->exists()
            || FixedAsset::query()->withoutGlobalScopes()->where('source_type', $morph)->where('source_id', $incoming->id)->exists()
            || AccountingOpenItem::query()->withoutGlobalScopes()->where('source_type', $morph)->where('source_id', $incoming->id)->exists();
    }
}
