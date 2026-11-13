<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiroCodeReader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\GiroCode;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Invoicing\Recognition\Contracts\QrCodeSource;
use App\Services\Invoicing\Split\IncomingInvoiceSplitter;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\File;
use CommonToolkit\Helper\FileSystem\FileTypes\ImageCropHelper;
use CommonToolkit\Parsers\Payment\EpcQrParser;
use Illuminate\Support\Facades\{DB, Log};
use PDFToolkit\Helper\PDFHelper;
use Throwable;

/**
 * GiroCode einer Eingangsrechnung lesen (Feature 208, MVP-1663): Seiten
 * rastern, QR-Codes lesen ({@see QrCodeSource}), die EPC-Nutzlast mit dem
 * Parser des common-toolkit prüfen — mit denselben Grenzen wie beim Erzeugen
 * (EPC069-12: Service-Tag, Version, Zeichensatz, Feldlängen, Betrag, 331 Byte).
 *
 * Grenzen gegen präparierte Dateien: nur die ersten {@see LEADING_PAGES}
 * Seiten und die letzte, je Seite einzeln und nur bis zur Kantenlänge
 * {@see IncomingInvoiceSplitter::MAX_PAGE_POINTS}, feste Auflösung, höchstens {@see MAX_CODES} Codes,
 * Nutzlasten über {@see MAX_PAYLOAD_BYTES} werden nicht geparst; die
 * Werkzeuge laufen mit den Zeitgrenzen des pdf-toolkit. Gelesen wird eine
 * Kopie im Temp-Verzeichnis, das Original bleibt unberührt.
 *
 * Die IBAN aus dem Code füllt `creditor_iban` nur, wenn das Feld leer ist,
 * die Prüfziffer stimmt und der Beleg keinen zweiten GiroCode mit anderer
 * IBAN trägt. Die Gegenprobe macht {@see GiroCodeCheck}.
 */
final class GiroCodeReader {
    public const string FOUND = 'found';

    public const string NONE = 'none';

    public const string UNREADABLE = 'unreadable';

    public const string INVALID = 'invalid';

    /** Beim Eingang gesetzt, bis der Job gelesen hat (Bestand vor Feature 208 bleibt ohne Lesung). */
    public const string PENDING = 'pending';

    /** Vordere Seiten, auf denen gesucht wird; dazu immer die letzte (Zahlteil am Ende). */
    public const int LEADING_PAGES = 3;

    /** Rasterauflösung — wie die Trennblatt-Erkennung des pdf-toolkit, fein genug für QR auf A4. */
    public const int DPI = 200;

    /** Höchstzahl ausgewerteter Codes je Beleg. */
    public const int MAX_CODES = 20;

    /** Größere Originale werden nicht gerastert. */
    public const int MAX_BYTES = 50 * 1024 * 1024;

    /** Größte Bildfläche eines Fotos bzw. Scans (≈ A4 mit 600 dpi). */
    public const int MAX_IMAGE_PIXELS = 40_000_000;

    /** Eine EPC-Nutzlast hat höchstens 331 Byte; Längeres ist kein GiroCode. */
    private const int MAX_PAYLOAD_BYTES = 1024;

    /** @var array<string, string> Inhaltstyp → Endung der Arbeitskopie */
    private const array EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/tiff' => 'tif',
        'image/gif' => 'gif',
    ];

    public function __construct(private readonly QrCodeSource $codes) {}

    /**
     * Lesen, ablegen und — wenn leer — die IBAN übernehmen. null, wenn das
     * Original weder PDF noch Bild ist (XML-Rechnung) oder fehlt.
     *
     * @return array<string, mixed>|null
     */
    public function apply(IncomingEInvoice $incoming): ?array {
        $reading = $this->read($incoming);
        if ($reading === null) {
            return null;
        }

        return DB::transaction(function () use ($incoming, $reading): array {
            $incoming->newQueryWithoutScopes()->whereKey($incoming->getKey())->lockForUpdate()->first();
            $incoming->refresh();

            $fill = [];
            // Nur solange der Eingang offen ist: nach Freigabe, Übergabe oder Zahllauf hätte die IBAN niemand mehr gesehen.
            $applied = $reading['status'] === self::FOUND
                && ($reading['iban_valid'] ?? false) === true
                && ($reading['conflicting'] ?? false) === false
                && trim((string) $incoming->creditor_iban) === ''
                && $incoming->direction !== DocumentDirection::Outgoing
                && in_array($incoming->status, [IncomingEInvoiceStatus::Received, IncomingEInvoiceStatus::Question], true)
                && $incoming->transferred_at === null
                && $incoming->paid_in_run_id === null;
            if ($applied) {
                $fill['creditor_iban'] = (string) $reading['iban'];
                if (trim((string) $incoming->creditor_bic) === '' && is_string($reading['bic'] ?? null)) {
                    $fill['creditor_bic'] = $reading['bic'];
                }
            }
            $reading['filled_iban'] = $applied;
            $incoming->forceFill([...$fill, 'girocode' => $reading])->save();

            $incoming->audit('incoming_einvoice.girocode_read', [
                'status' => $reading['status'],
                'page' => $reading['page'] ?? null,
                'iban_applied' => $applied,
                'findings' => app(GiroCodeCheck::class)->findings($incoming),
            ]);

            return $reading;
        });
    }

    /** Noch zu lesen: beim Eingang vorgemerkt oder nach „ist eine Rechnung“ ohne Lesung. */
    public static function isAwaited(IncomingEInvoice $incoming): bool {
        $status = ((array) $incoming->girocode)['status'] ?? null;

        return $incoming->girocode === null || $status === self::PENDING;
    }

    /**
     * Nur lesen, nichts speichern.
     *
     * @return array<string, mixed>|null
     */
    public function read(IncomingEInvoice $incoming): ?array {
        $original = $incoming->storedOriginal();
        if ($original === null || $original['contents'] === '') {
            return null;
        }
        // Nur die geprüfte Datei: eine später im DMS hochgeladene Version trägt einen anderen Hash.
        if (! hash_equals($incoming->sha256, (string) CryptoHelper::hash($original['contents']))) {
            return self::reading(self::UNREADABLE, ['reason' => 'changed']);
        }
        $extension = self::EXTENSIONS[File::mimeTypeFromContent($original['contents']) ?: ''] ?? null;
        if ($extension === null) {
            return null;
        }
        if (strlen($original['contents']) > self::MAX_BYTES) {
            return self::reading(self::UNREADABLE, ['reason' => 'too_large']);
        }
        if (! $this->codes->isAvailable()) {
            return self::reading(self::UNREADABLE, ['reason' => 'tool_missing']);
        }

        try {
            /** @var array<int, list<array{type: string, data: string}>>|null $found */
            $found = File::withTemp(
                $original['contents'],
                fn (string $path): ?array => $extension === 'pdf' ? $this->scanPdf($path) : $this->scanImage($path),
                'girocode-',
                $extension,
            );
        } catch (Throwable $e) {
            Log::info('GiroCode nicht lesbar', ['incoming_id' => $incoming->id, 'error' => $e->getMessage()]);

            return self::reading(self::UNREADABLE, ['reason' => 'scan_failed']);
        }
        if ($found === null) {
            return self::reading(self::UNREADABLE, ['reason' => $extension === 'pdf' ? 'page_size' : 'too_large']);
        }

        return self::evaluate($found);
    }

    /**
     * Gefundene Codes auswerten: der erste gültige GiroCode zählt; ein weiterer
     * mit anderer IBAN macht die Lesung widersprüchlich (Sperre im Zahllauf).
     *
     * @param  array<int, list<array{type: string, data: string}>>  $found  Seite → Codes
     * @return array<string, mixed>
     */
    public static function evaluate(array $found): array {
        ksort($found);
        $payments = [];
        $invalid = 0;
        $seen = 0;
        foreach ($found as $page => $codes) {
            foreach ($codes as $code) {
                if (++$seen > self::MAX_CODES) {
                    break 2;
                }
                $data = $code['data'];
                if (strlen($data) > self::MAX_PAYLOAD_BYTES || ! str_starts_with(ltrim($data), 'BCD')) {
                    continue;
                }
                $payment = EpcQrParser::tryParse($data);
                if ($payment === null) {
                    $invalid++;

                    continue;
                }
                $payments[] = ['page' => (int) $page, 'payment' => $payment];
            }
        }

        if ($payments === []) {
            return self::reading($invalid > 0 ? self::INVALID : self::NONE, ['codes' => min($seen, self::MAX_CODES)]);
        }
        $first = $payments[0]['payment'];
        $ibans = array_values(array_unique(array_map(static fn (array $hit): string => $hit['payment']->getIban(), $payments)));

        return self::reading(self::FOUND, [
            'page' => $payments[0]['page'],
            'version' => $first->getVersion(),
            'iban' => $first->getIban(),
            'iban_valid' => $first->isIbanValid(),
            'bic' => $first->getBic(),
            'name' => self::displayText($first->getName()),
            'amount' => $first->getAmount(),
            'reference' => self::displayText($first->getReference()),
            'remittance' => self::displayText($first->getRemittance()),
            'codes' => count($payments),
            'conflicting' => count($ibans) > 1,
        ]);
    }

    /**
     * Ein Bild nur bis {@see MAX_IMAGE_PIXELS} (Maße ohne ImageMagick gelesen);
     * ein präpariertes Riesenbild käme sonst ungebremst in zbarimg.
     *
     * @return array<int, list<array{type: string, data: string}>>|null null = zu groß oder unlesbar
     */
    private function scanImage(string $path): ?array {
        $size = ImageCropHelper::getImageDimensions($path);
        if ($size === null || $size['width'] * $size['height'] > self::MAX_IMAGE_PIXELS) {
            return null;
        }

        return [1 => $this->codes->scanImage($path)];
    }

    /**
     * Seite für Seite, nur Seiten bis {@see IncomingInvoiceSplitter::MAX_PAGE_POINTS}:
     * das Toolkit rastert einen Bereich zusammenhängend, eine übergroße Seite
     * dazwischen würde sonst mitgerastert.
     *
     * @return array<int, list<array{type: string, data: string}>>|null null = alle Seiten zu groß
     */
    private function scanPdf(string $path): ?array {
        $pages = PDFHelper::getPageCount($path);
        if ($pages < 1) {
            return [];
        }
        $targets = range(1, min(self::LEADING_PAGES, $pages));
        if ($pages > self::LEADING_PAGES) {
            $targets[] = $pages;
        }
        // Größere von MediaBox und CropBox (Sicherheitsprüfung H1); ohne lesbare Maße wird nicht gerastert.
        $targets = array_values(array_filter(
            $targets,
            static fn (int $page): bool => IncomingInvoiceSplitter::pagesWithinLimits($path, $page, $page),
        ));
        if ($targets === []) {
            return null;
        }

        $found = [];
        foreach ($targets as $page) {
            $found += $this->codes->scanPdf($path, [$page], self::DPI);
        }

        return $found;
    }

    /** Freitext aus dem Code ohne Steuer- und Formatzeichen (Bidi, Nullbreite) — er wird angezeigt und verglichen. */
    private static function displayText(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $clean = (string) preg_replace('/\p{Cf}+/u', '', (string) preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}\x{2028}\x{2029}]/u', ' ', mb_scrub($value, 'UTF-8')));

        return trim($clean) === '' ? null : trim($clean);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function reading(string $status, array $values = []): array {
        return ['status' => $status, ...$values, 'read_at' => now()->toIso8601String()];
    }
}
