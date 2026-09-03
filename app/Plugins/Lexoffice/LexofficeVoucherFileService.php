<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherFileService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\LexofficeVoucher;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use RuntimeException;

/**
 * Lädt das hinterlegte Belegbild / -dokument eines Lexoffice-Belegs als
 * Binär-String herunter.
 *
 * Lexoffice unterscheidet zwei Quellen:
 *   - Einkaufsbelege & generische Belege (purchaseinvoice, purchasecreditnote,
 *     voucher) liegen unter `/vouchers/{id}` und tragen ein `files`-Array mit
 *     den hochgeladenen Datei-IDs (das eigentliche "Belegbild").
 *   - Verkaufsdokumente (salesinvoice, salescreditnote, …) besitzen ein
 *     gerendertes PDF, das über `/{endpoint}/{id}/document` → `documentFileId`
 *     adressiert wird.
 *
 * In beiden Fällen liefert `GET /files/{fileId}` schließlich die Binärdatei.
 */
class LexofficeVoucherFileService {
    /**
     * voucherType → Verkaufsdokument-Endpoint (für den /document-Weg).
     *
     * @var array<string, string>
     */
    private const SALES_DOCUMENT_ENDPOINTS = [
        'salesinvoice' => 'invoices',
        'invoice' => 'invoices',
        'downpaymentinvoice' => 'invoices',
        'salescreditnote' => 'credit-notes',
        'creditnote' => 'credit-notes',
        'orderconfirmation' => 'order-confirmations',
        'quotation' => 'quotations',
        'deliverynote' => 'delivery-notes',
    ];

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    public function isConfigured(): bool {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * Lädt das Belegbild/-dokument herunter.
     *
     * @return array{body: string, content_type: string, extension: string}
     */
    public function download(LexofficeVoucher $voucher): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $fileId = $this->resolveFileId($voucher);

        $response = $this->api()->getResponse(
            $this->baseUrl . '/files/' . $fileId,
            [],
            ['headers' => ['Accept' => 'application/pdf, image/png, image/jpeg, image/gif, image/tiff, application/xml, */*']],
        );

        if ($response->status() === 429) {
            throw new LexofficeRateLimitException();
        }

        if (! $response->successful()) {
            throw new RuntimeException('Lexoffice file fetch failed: ' . $response->status());
        }

        $contentType = (string) ($response->header('Content-Type') ?: 'application/octet-stream');
        $contentType = trim(explode(';', $contentType)[0]);

        return [
            'body' => (string) $response->body(),
            'content_type' => $contentType,
            'extension' => $this->extensionFor($contentType),
        ];
    }

    /**
     * Sichert das Belegbild lokal (MVP-690, Vollscan G3 — GoBD nach
     * Vertragsende). Idempotent; ein Beleg ohne Belegbild wird als „geprüft"
     * markiert (file_materialized_at gesetzt, file_path NULL).
     *
     * @return bool true, wenn (jetzt) ein lokales Belegbild vorliegt
     */
    public function materialize(LexofficeVoucher $voucher): bool {
        if ($voucher->file_materialized_at !== null) {
            return $voucher->file_path !== null
                && \Illuminate\Support\Facades\Storage::disk('local')->exists($voucher->file_path);
        }

        try {
            $file = $this->download($voucher);
        } catch (\Throwable $e) {
            // Kein Belegbild (404/fehlende Datei-Referenz): als geprüft
            // markieren — andere Fehler (Auth/Netz) nach oben, der Command
            // zählt sie und der Abschluss-Blocker bleibt bestehen.
            if (str_contains($e->getMessage(), '404') || $e instanceof \RuntimeException && str_contains($e->getMessage(), 'file')) {
                $voucher->forceFill(['file_materialized_at' => now(), 'file_path' => null])->save();

                return false;
            }
            throw $e;
        }

        $path = sprintf('lexoffice-vouchers/%d/%d.%s', $voucher->organization_id, $voucher->id, $file['extension']);
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $file['body']);
        $voucher->forceFill(['file_path' => $path, 'file_materialized_at' => now()])->save();

        return true;
    }

    /**
     * Lokal materialisiertes Belegbild, falls vorhanden — der Leseweg nach
     * dem Buchhaltungswechsel (kein Live-API-Zugriff mehr nötig).
     *
     * @return array{body: string, content_type: string, extension: string}|null
     */
    public function localFile(LexofficeVoucher $voucher): ?array {
        if ($voucher->file_path === null) {
            return null;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (! $disk->exists($voucher->file_path)) {
            return null;
        }

        $extension = pathinfo($voucher->file_path, PATHINFO_EXTENSION) ?: 'pdf';

        return [
            'body' => (string) $disk->get($voucher->file_path),
            'content_type' => match ($extension) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'tif', 'tiff' => 'image/tiff',
                'xml' => 'application/xml',
                default => 'application/pdf',
            },
            'extension' => $extension,
        ];
    }

    private function resolveFileId(LexofficeVoucher $voucher): string {
        $type = (string) ($voucher->voucher_type ?? '');
        $endpoint = self::SALES_DOCUMENT_ENDPOINTS[$type] ?? null;

        // Verkaufsdokumente liefern ihr PDF über /document (fehlt es: 406); generische Belege über das
        // /vouchers/{id}-files-Array. Beide Wege als Fallback, damit die Anzeige quellenunabhängig ist.
        $fileId = $endpoint !== null
            ? $this->fileIdFromDocument($endpoint, $voucher->external_id)
            : '';

        if ($fileId === '') {
            $fileId = $this->fileIdFromVoucher($voucher->external_id);
        }

        if ($fileId === '' && $endpoint === null) {
            // Letzter Versuch: vielleicht ist es doch ein Sales-Document.
            foreach (array_unique(array_values(self::SALES_DOCUMENT_ENDPOINTS)) as $candidate) {
                $fileId = $this->fileIdFromDocument($candidate, $voucher->external_id);
                if ($fileId !== '') {
                    break;
                }
            }
        }

        if ($fileId === '') {
            throw new RuntimeException('Lexoffice voucher has no attached file.');
        }

        return $fileId;
    }

    private function fileIdFromVoucher(string $externalId): string {
        $response = $this->api()->getResponse($this->baseUrl . '/vouchers/' . $externalId);

        if (! $response->successful()) {
            return '';
        }

        $files = $response->json('files');

        if (is_array($files) && $files !== []) {
            $first = $files[0];
            if (is_array($first)) {
                return (string) ($first['id'] ?? $first['fileId'] ?? '');
            }

            return (string) $first;
        }

        return '';
    }

    private function fileIdFromDocument(string $endpoint, string $externalId): string {
        $response = $this->api()->getResponse($this->baseUrl . '/' . $endpoint . '/' . $externalId . '/document');

        // 406 = kein gerendertes Dokument (z. B. Entwurf) — kein harter Fehler, Aufrufer nutzt andere Quellen.
        if (! $response->successful()) {
            return '';
        }

        return (string) ($response->json('documentFileId') ?? '');
    }

    /**
     * Basis-HTTP-Client auf dem `php-api-toolkit`-Fundament: Retry bei
     * Rate-Limit (429, inkl. `Retry-After`) und transienten Verbindungsfehlern
     * bringt {@see PluginApiClient} bereits mit; die (Fehler-)Antwort kommt
     * regulär zurück und wird vom Aufrufer behandelt.
     */
    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $this->baseUrl, LexofficeConfig::requestInterval());
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiKey));
            // Der Antwort-Body geht unverändert an den Browser. Die
            // SSRF-Schranke prüft die Basis-URL — eine Weiterleitung führte
            // danach an jedes beliebige Ziel, und der Inhalt käme beim
            // Aufrufer an (Sicherheitsscan 2026-08-23, S-10).
            $this->api->setFollowRedirects(false);
        }

        return $this->api;
    }

    private function extensionFor(string $contentType): string {
        return match ($contentType) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/tiff' => 'tif',
            default => 'bin',
        };
    }
}
