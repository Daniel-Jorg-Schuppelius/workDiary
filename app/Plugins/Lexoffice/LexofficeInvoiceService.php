<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\{ExternalReference, Invoice};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use RuntimeException;

/**
 * Delegiert die Rechnungserstellung an Lexoffice (POST /v1/invoices).
 * Nach erfolgreichem Push:
 *  - external_id wird in ExternalReference gespeichert
 *  - die Lexoffice-Rechnungsnummer wird (falls vorhanden) in die lokale
 *    Invoice übernommen
 *  - Status wechselt von draft → issued
 *
 * Bewusst HTTP-basiert (statt SDK-Entities), weil wir auch das PDF
 * herunterladen und Status pullen wollen — der SDK-Workflow ist hier eher
 * im Weg.
 */
class LexofficeInvoiceService {
    public const EXT_TYPE_INVOICE = 'invoice';

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly LexofficeInvoiceMapper $mapper,
        private readonly ?string $apiKey,
        /** @var array<string, mixed> */
        private readonly array $defaults = [],
        private readonly string $baseUrl = 'https://api.lexoffice.io/v1',
    ) {}

    public function isConfigured(): bool {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $this->baseUrl, LexofficeConfig::requestInterval());
            $this->api->setAuthentication(new BearerAuthentication((string) $this->apiKey));
        }

        return $this->api;
    }

    /**
     * Erzeugt in Lexoffice eine Rechnung aus der lokalen Invoice. Wenn
     * $finalize true ist, wird die Rechnung sofort festgeschrieben (kein
     * Draft); sonst landet sie als Entwurf in Lexoffice.
     *
     * @return array{external_id: string, payload: array<string, mixed>}
     */
    public function publish(Invoice $invoice, ?string $externalContactId = null, bool $finalize = true): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $payload = $this->mapper->toPayload($invoice, $externalContactId, $this->defaults);

        $url = $this->baseUrl . '/invoices' . ($finalize ? '?finalize=true' : '');
        $response = $this->api()->postJson($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Lexoffice invoice create failed: ' . $response->status() . ' ' . $response->body());
        }

        $body = (array) ($response->json() ?? []);
        $externalId = (string) ($body['id'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException('Lexoffice invoice create returned no id.');
        }

        ExternalReference::updateOrCreate(
            [
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => self::EXT_TYPE_INVOICE,
                'referenceable_type' => $invoice->getMorphClass(),
                'referenceable_id' => $invoice->getKey(),
            ],
            [
                'organization_id' => $invoice->organization_id,
                'external_id' => $externalId,
                'payload' => $body + ['_request' => $payload],
                'synced_at' => now(),
            ],
        );

        $fetched = $this->fetchInvoice($externalId);
        $remoteNumber = $fetched['voucherNumber'] ?? null;
        // Ausstellung über die einzige Schreibstelle (Vollscan 2026-08-23, B1):
        // Partei-Snapshot, Fälligkeit und tax_context frieren jetzt auch beim
        // Lexoffice-Push ein; die Lexoffice-Nummer kommt im selben Save mit.
        $extra = is_string($remoteNumber) && $remoteNumber !== '' ? ['number' => $remoteNumber] : [];
        app(\App\Services\Invoicing\InvoiceIssueService::class)->issue($invoice, $extra);

        return ['external_id' => $externalId, 'payload' => $fetched];
    }

    /**
     * Holt die Lexoffice-Rechnung (z. B. für Status-Sync).
     *
     * @return array<string, mixed>
     */
    public function fetchInvoice(string $externalId): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Lexoffice API key is not configured.');
        }
        $response = $this->api()->getResponse($this->baseUrl . '/invoices/' . $externalId);

        if (! $response->successful()) {
            throw new RuntimeException('Lexoffice invoice fetch failed: ' . $response->status() . ' ' . $response->body());
        }

        return (array) ($response->json() ?? []);
    }

    /**
     * Holt das PDF einer finalisierten Lexoffice-Rechnung als Binär-String.
     * Lexoffice braucht dazu zwei Schritte:
     *   1. GET /invoices/{id}/document → liefert {documentFileId}
     *   2. GET /files/{documentFileId} → binäres PDF
     */
    public function downloadPdf(string $externalId): string {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Lexoffice API key is not configured.');
        }

        $meta = $this->api()->getResponse($this->baseUrl . '/invoices/' . $externalId . '/document');

        if (! $meta->successful()) {
            throw new RuntimeException('Lexoffice invoice document metadata fetch failed: ' . $meta->status());
        }
        $fileId = (string) ($meta->json('documentFileId') ?? '');
        if ($fileId === '') {
            throw new RuntimeException('Lexoffice invoice document has no documentFileId.');
        }

        $pdf = $this->api()->getResponse(
            $this->baseUrl . '/files/' . $fileId,
            [],
            ['headers' => ['Accept' => 'application/pdf']],
        );

        if (! $pdf->successful()) {
            throw new RuntimeException('Lexoffice file fetch failed: ' . $pdf->status());
        }

        return (string) $pdf->body();
    }
}
