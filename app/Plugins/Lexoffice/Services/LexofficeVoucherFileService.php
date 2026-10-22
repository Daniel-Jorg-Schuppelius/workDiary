<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherFileService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\ApiException;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Exceptions\{LexofficeNotConfiguredException, LexofficeRateLimitException, LexofficeVoucherFileMissingException};
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use CommonToolkit\Helper\FileSystem\File;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\Documents\{CreditNotesEndpoint, DeliveryNotesEndpoint, InvoicesEndpoint, OrderConfirmationsEndpoint, QuotationsEndpoint};
use Lexoffice\API\Endpoints\{FilesEndpoint, VouchersEndpoint};
use Lexoffice\Contracts\Abstracts\API\DocumentEndpointAbstract;
use Lexoffice\Entities\Files\FileContent;
use RuntimeException;

/**
 * Lädt das hinterlegte Belegbild / -dokument eines Lexoffice-Belegs als
 * Binär-String herunter.
 *
 * Lexoffice unterscheidet zwei Quellen:
 *   - Einkaufsbelege & generische Belege (purchaseinvoice, purchasecreditnote,
 *     voucher) liegen unter `/vouchers/{id}` und tragen ein `files`-Array mit
 *     den hochgeladenen Datei-IDs (das eigentliche "Belegbild").
 *   - Verkaufsdokumente (salesinvoice, salescreditnote, …) liefern ihr PDF
 *     über `/{endpoint}/{id}/file` (`/document` ist laut Referenz veraltet).
 *
 * Das Belegbild selbst liefert `GET /files/{fileId}`.
 */
class LexofficeVoucherFileService {
    /**
     * voucherType → Verkaufsdokument-Endpoint (für den /file-Weg).
     *
     * @var array<string, class-string<DocumentEndpointAbstract>>
     */
    private const SALES_DOCUMENT_ENDPOINTS = [
        'salesinvoice' => InvoicesEndpoint::class,
        'invoice' => InvoicesEndpoint::class,
        'downpaymentinvoice' => InvoicesEndpoint::class,
        'salescreditnote' => CreditNotesEndpoint::class,
        'creditnote' => CreditNotesEndpoint::class,
        'orderconfirmation' => OrderConfirmationsEndpoint::class,
        'quotation' => QuotationsEndpoint::class,
        'deliverynote' => DeliveryNotesEndpoint::class,
    ];

    private const DOWNLOAD_ACCEPT = 'application/pdf, image/png, image/jpeg, image/gif, image/tiff, application/xml, */*';

    /**
     * Belegtypen, die inline angezeigt werden dürfen. Alles andere wird zum
     * Download — auch `image/svg+xml`: SVG ist ein Dokument mit Skriptfähigkeit,
     * kein Bild im harmlosen Sinn.
     */
    public const INLINE_CONTENT_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/tiff',
        'image/webp',
    ];

    public const FALLBACK_CONTENT_TYPE = 'application/octet-stream';

    private ?Client $client = null;

    /** @param  int|null  $organizationId  null = gebundene Organisation bzw. Einzelplatz-Konfiguration */
    public function __construct(
        private readonly ?int $organizationId,
    ) {}

    public function isConfigured(): bool {
        return app(LexofficeClientFactory::class)->isConfigured($this->organizationId);
    }

    /**
     * Gemeldeter Typ der Quelle gegen die Positivliste (Sicherheitsscan
     * 2026-08-23, S-64): sonst rendert der Browser z. B. `text/html` inline im
     * eigenen Origin.
     */
    public static function safeContentType(string $reported): string {
        // Parameter abschneiden (`; charset=…`) und normalisieren.
        $type = mb_strtolower(trim(explode(';', $reported, 2)[0]));

        return in_array($type, self::INLINE_CONTENT_TYPES, true) ? $type : self::FALLBACK_CONTENT_TYPE;
    }

    /**
     * Lädt das Belegbild/-dokument herunter.
     *
     * @return array{body: string, content_type: string, extension: string}
     */
    public function download(LexofficeVoucher $voucher): array {
        if (! $this->isConfigured()) {
            throw new LexofficeNotConfiguredException();
        }

        $file = $this->fetchFile($voucher);
        $contentType = trim(explode(';', $file->getContentType() ?? 'application/octet-stream')[0]);

        return [
            'body' => $file->getContent(),
            'content_type' => $contentType,
            'extension' => File::extensionForMimeType($contentType) ?? 'bin',
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
        } catch (LexofficeVoucherFileMissingException) {
            // Kein Belegbild (404/fehlende Datei-Referenz): als geprüft
            // markieren. Alle anderen Fehler (Auth/Netz/Server) laufen durch —
            // der Command zählt sie und der Abschluss-Blocker bleibt bestehen.
            $voucher->forceFill(['file_materialized_at' => now(), 'file_path' => null])->save();

            return false;
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
            'content_type' => File::mimeTypeForExtension($extension) ?? 'application/pdf',
            'extension' => $extension,
        ];
    }

    private function fetchFile(LexofficeVoucher $voucher): FileContent {
        $endpoint = self::SALES_DOCUMENT_ENDPOINTS[(string) ($voucher->voucher_type ?? '')] ?? null;

        // Verkaufsdokumente liefern ihr PDF über /file (Entwurf: 409), generische Belege ihr Belegbild über
        // das files-Array von /vouchers/{id}. Beide Wege als Rückfall, damit die Anzeige quellenunabhängig ist.
        $file = $endpoint !== null ? $this->documentFile(new $endpoint($this->client()), $voucher->external_id) : null;
        $file ??= $this->uploadedFile($voucher->external_id);

        if ($file === null && $endpoint === null) {
            // Letzter Versuch: vielleicht ist es doch ein Verkaufsdokument.
            foreach (array_unique(array_values(self::SALES_DOCUMENT_ENDPOINTS)) as $candidate) {
                $file = $this->documentFile(new $candidate($this->client()), $voucher->external_id);
                if ($file !== null) {
                    break;
                }
            }
        }

        return $file ?? throw new LexofficeVoucherFileMissingException('Lexoffice voucher has no attached file.');
    }

    private function documentFile(DocumentEndpointAbstract $endpoint, string $externalId): ?FileContent {
        try {
            return $endpoint->downloadContent(new ID($externalId), 'application/pdf');
        } catch (ApiException $e) {
            $this->assertLookupMiss($e);

            return null;
        }
    }

    private function uploadedFile(string $externalId): ?FileContent {
        try {
            $files = (new VouchersEndpoint($this->client()))->get(new ID($externalId))->getFiles()?->getValues() ?? [];
        } catch (ApiException $e) {
            $this->assertLookupMiss($e);

            return null;
        }
        if (! isset($files[0])) {
            return null;
        }

        $fileId = (string) $files[0]->getValue();
        try {
            return (new FilesEndpoint($this->client()))->downloadContent(new ID($fileId), self::DOWNLOAD_ACCEPT);
        } catch (ApiException $e) {
            throw match ($e->getCode()) {
                429 => new LexofficeRateLimitException(),
                404 => new LexofficeVoucherFileMissingException('Lexoffice file ' . $fileId . ' does not exist (404).'),
                default => new RuntimeException('Lexoffice file fetch failed: ' . $e->getCode()),
            };
        }
    }

    /**
     * Fehlantworten beim Nachschlagen der Datei: 404/406/409 heißen „an
     * dieser Quelle nichts" (der Aufrufer versucht die nächste), 429 ist
     * transient — alles andere (401/403/5xx) ist ein echter Fehler und darf
     * nicht als „kein Belegbild" enden.
     */
    private function assertLookupMiss(ApiException $e): void {
        if ($e->getCode() === 429) {
            throw new LexofficeRateLimitException();
        }
        if (! in_array($e->getCode(), [404, 406, 409], true)) {
            throw new RuntimeException('Lexoffice voucher lookup failed: ' . $e->getCode());
        }
    }

    private function client(): Client {
        if ($this->client === null) {
            $this->client = app(LexofficeClientFactory::class)->for($this->organizationId);
            // Der Antwort-Body geht unverändert an den Browser. Die SSRF-Schranke
            // prüft die Basis-URL; einer Weiterleitung folgt dieser Client nicht
            // (Sicherheitsscan 2026-08-23, S-10). Je Anfrage, weil der Transport
            // injiziert ist und setFollowRedirects() ihn nicht erreicht.
            $this->client->onRequest(static fn (string $method, string $uri, array $options): array => ['allow_redirects' => false] + $options);
        }

        return $this->client;
    }
}
