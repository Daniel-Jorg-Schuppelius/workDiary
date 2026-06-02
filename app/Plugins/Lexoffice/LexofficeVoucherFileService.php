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

use App\Models\LexofficeVoucher;
use Illuminate\Support\Facades\Http;
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

        $response = Http::withToken((string) $this->apiKey)
            ->withHeaders(['Accept' => 'application/pdf, image/png, image/jpeg, image/gif, image/tiff, application/xml, */*'])
            ->get($this->baseUrl . '/files/' . $fileId);

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

    private function resolveFileId(LexofficeVoucher $voucher): string {
        $type = (string) ($voucher->voucher_type ?? '');
        $endpoint = self::SALES_DOCUMENT_ENDPOINTS[$type] ?? null;

        // Verkaufsdokumente (Rechnungen, Gutschriften …) liefern ihr gerendertes
        // PDF über den /document-Endpunkt. Liegt (noch) keines vor, antwortet
        // Lexoffice mit 406; generische Belege tragen ihre Datei stattdessen im
        // /vouchers/{id}-files-Array. Beide Wege werden als Fallback versucht,
        // damit die Anzeige unabhängig von der Belegquelle funktioniert.
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
        $response = Http::withToken((string) $this->apiKey)
            ->acceptJson()
            ->get($this->baseUrl . '/vouchers/' . $externalId);

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
        $response = Http::withToken((string) $this->apiKey)
            ->acceptJson()
            ->get($this->baseUrl . '/' . $endpoint . '/' . $externalId . '/document');

        // 406 = kein gerendertes Dokument verfügbar (z. B. Entwurf). Das ist
        // kein harter Fehler — der Aufrufer fällt auf andere Quellen zurück.
        if (! $response->successful()) {
            return '';
        }

        return (string) ($response->json('documentFileId') ?? '');
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
