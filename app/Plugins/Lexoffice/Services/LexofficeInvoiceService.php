<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Sales\OrderConfirmationStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Support\PluginApiClient;
use App\Support\MorphMap;
use RuntimeException;

/**
 * Überträgt Rechnungen an Lexoffice (POST /v1/invoices mit der AB bzw. dem
 * Angebot als Vorgänger; Gutschrift und Storno als Rechnungskorrektur
 * POST /v1/credit-notes mit Bezug auf die übertragene Ursprungsrechnung,
 * MVP-1154/1166). Die Verknüpfung steht in einer ExternalReference, die
 * Lexoffice-Nummer übernimmt das lokale Ausstellen.
 *
 * Bewusst HTTP-basiert (statt SDK-Entities), weil wir auch das PDF
 * herunterladen und Status pullen wollen — der SDK-Workflow ist hier eher
 * im Weg.
 */
class LexofficeInvoiceService {
    /** Gilt für jeden übertragenen lokalen Beleg, auch Korrekturen; die Ressource steht in `payload._resource`. */
    public const EXT_TYPE_INVOICE = 'invoice';

    public const RESOURCE_INVOICES = 'invoices';

    public const RESOURCE_CREDIT_NOTES = 'credit-notes';

    private ?PluginApiClient $api = null;

    public function __construct(
        private readonly LexofficeInvoiceMapper $mapper,
        private readonly ?string $apiKey,
        /** @var array<string, mixed> */
        private readonly array $defaults = [],
        private readonly string $baseUrl = 'https://api.lexware.io/v1',
        private readonly ?float $requestInterval = null,
    ) {}

    public function isConfigured(): bool {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    private function api(): PluginApiClient {
        return $this->api ??= app(LexofficeClientFactory::class)->make((string) $this->apiKey, $this->baseUrl, $this->requestInterval);
    }

    /**
     * Überträgt die lokale Rechnung festgeschrieben an Lexoffice und liefert
     * die Lexoffice-Nummer (Phase 145, MVP-1166). Ausgestellt wird lokal über
     * {@see \App\Services\Invoicing\InvoiceIssueService::issue()}, das diese
     * Übertragung über den {@see LexofficeDocumentIssuer} anstößt. Eine schon
     * verknüpfte Rechnung wird nicht erneut angelegt.
     *
     * @return array{external_id: string, number: string, payload: array<string, mixed>}
     */
    public function transfer(Invoice $invoice, ?string $externalContactId = null): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_not_configured'));
        }

        $correction = $invoice->isCreditNote() || $invoice->type === Invoice::TYPE_CANCELLATION;
        $resource = $correction ? self::RESOURCE_CREDIT_NOTES : self::RESOURCE_INVOICES;
        $reference = ExternalReference::query()
            ->forPlugin($invoice->organization_id, LexofficePlugin::ID, self::EXT_TYPE_INVOICE)
            ->forReferenceable($invoice)
            ->first();
        if ($reference instanceof ExternalReference && (string) $reference->external_number !== '') {
            return ['external_id' => (string) $reference->external_id, 'number' => (string) $reference->external_number, 'payload' => (array) $reference->payload];
        }

        if (! $reference instanceof ExternalReference) {
            $contactId = $externalContactId ?? ExternalReference::query()
                ->forPlugin($invoice->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
                ->forReferenceable($invoice->customer)
                ->value('external_id');
            $payload = $correction
                ? $this->mapper->toCreditNotePayload($invoice, $contactId, $this->defaults)
                : $this->mapper->toPayload($invoice, $contactId, $this->defaults);
            // Korrektur ohne übertragene Ursprungsrechnung entsteht ohne Bezug (Original aus der Zeit lokaler Nummern).
            $preceding = $correction ? $this->precedingVoucherId($invoice) : $this->orderVoucherId($invoice);
            $response = $this->create($resource, $payload, $preceding);
            // Lexoffice lehnt einen ungültigen Vorgänger mit 406 ab (etwa ein Angebot mit Optionspositionen) — dann ohne Bezug.
            if ($response->status() === 406 && $preceding !== null && ! $correction) {
                $response = $this->create($resource, $payload, null);
            }
            if (! $response->successful()) {
                throw new RuntimeException('Lexoffice ' . $resource . ' create failed: ' . $response->status() . ' ' . mb_substr((string) $response->body(), 0, 500));
            }
            $externalId = (string) ($response->json('id') ?? '');
            if ($externalId === '') {
                throw new RuntimeException('Lexoffice invoice create returned no id.');
            }
            $reference = ExternalReference::create([
                'organization_id' => $invoice->organization_id,
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => self::EXT_TYPE_INVOICE,
                'referenceable_type' => $invoice->getMorphClass(),
                'referenceable_id' => $invoice->getKey(),
                'external_id' => $externalId,
                'payload' => ['_request' => $payload, '_resource' => $resource],
                'synced_at' => now(),
            ]);
        }

        $fetched = $this->fetchInvoice((string) $reference->external_id, (string) ($reference->payload['_resource'] ?? $resource));
        $number = (string) ($fetched['voucherNumber'] ?? '');
        if ($number === '') {
            throw new RuntimeException((string) __('numbering.error.no_number', ['program' => 'Lexoffice']));
        }
        $reference->forceFill([
            'external_number' => $number,
            'payload' => $fetched + ['_resource' => (string) ($reference->payload['_resource'] ?? $resource)],
            'synced_at' => now(),
        ])->save();

        return ['external_id' => (string) $reference->external_id, 'number' => $number, 'payload' => $fetched];
    }

    /**
     * Übertragen und mit der Lexoffice-Nummer ausstellen — für Belege, die
     * Lexoffice unabhängig vom Nummernkreis führt (Retainer, Feature 098).
     *
     * @return array{external_id: string, payload: array<string, mixed>}
     */
    public function publish(Invoice $invoice, ?string $externalContactId = null): array {
        $result = $this->transfer($invoice, $externalContactId);
        app(\App\Services\Invoicing\InvoiceIssueService::class)->issue($invoice, ['number' => $result['number']]);

        return ['external_id' => $result['external_id'], 'payload' => $result['payload']];
    }

    /** @param array<string, mixed> $payload */
    private function create(string $resource, array $payload, ?string $preceding): \Illuminate\Http\Client\Response {
        $query = array_filter(['precedingSalesVoucherId' => $preceding, 'finalize' => 'true']);

        return $this->api()->postJson($this->baseUrl . '/' . $resource . '?' . http_build_query($query), $payload);
    }

    /**
     * Vorgänger einer Rechnung in Lexoffice: die übertragene AB des Angebots,
     * sonst das übertragene Angebot (Phase 145, MVP-1166).
     */
    private function orderVoucherId(Invoice $invoice): ?string {
        if ($invoice->quote_id === null) {
            return null;
        }
        $confirmationId = OrderConfirmation::query()
            ->where('organization_id', $invoice->organization_id)
            ->where('quote_id', $invoice->quote_id)
            ->where('status', OrderConfirmationStatus::Confirmed)
            ->latest('id')
            ->value('id');
        $candidates = array_filter([
            [LexofficeOrderConfirmationService::EXT_TYPE_ORDER_CONFIRMATION, MorphMap::alias(OrderConfirmation::class), $confirmationId],
            [LexofficeQuotationService::EXT_TYPE_QUOTATION, MorphMap::alias(Quote::class), $invoice->quote_id],
        ], static fn (array $candidate): bool => $candidate[2] !== null);
        foreach ($candidates as [$type, $alias, $id]) {
            $externalId = ExternalReference::query()
                ->forPlugin($invoice->organization_id, LexofficePlugin::ID, $type)
                ->where('referenceable_type', $alias)
                ->where('referenceable_id', $id)
                ->whereNotNull('external_number')
                ->value('external_id');
            if (is_string($externalId) && $externalId !== '') {
                return $externalId;
            }
        }

        return null;
    }

    /**
     * Holt die Lexoffice-Rechnung (z. B. für Status-Sync).
     *
     * @return array<string, mixed>
     */
    public function fetchInvoice(string $externalId, string $resource = self::RESOURCE_INVOICES): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Lexoffice API key is not configured.');
        }
        $response = $this->api()->getResponse($this->baseUrl . '/' . $resource . '/' . $externalId);

        if (! $response->successful()) {
            throw new RuntimeException('Lexoffice invoice fetch failed: ' . $response->status() . ' ' . $response->body());
        }

        return (array) ($response->json() ?? []);
    }

    /**
     * PDF eines festgeschriebenen Lexoffice-Belegs: `GET /{art}/{id}/file`
     * (seit 08/2025; `/document` mit `documentFileId` ist abgekündigt, Befund B7).
     */
    public function downloadPdf(string $externalId, string $resource = self::RESOURCE_INVOICES): string {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Lexoffice API key is not configured.');
        }

        $pdf = $this->api()->getResponse(
            $this->baseUrl . '/' . $resource . '/' . $externalId . '/file',
            [],
            ['headers' => ['Accept' => 'application/pdf']],
        );
        if (! $pdf->successful()) {
            throw new RuntimeException('Lexoffice file fetch failed: ' . $pdf->status());
        }

        return (string) $pdf->body();
    }

    /** Lexoffice-ID der übertragenen Ursprungsrechnung einer Korrektur. */
    private function precedingVoucherId(Invoice $invoice): ?string {
        if ($invoice->parent_invoice_id === null) {
            return null;
        }

        $id = ExternalReference::query()
            ->forPlugin($invoice->organization_id, LexofficePlugin::ID, self::EXT_TYPE_INVOICE)
            ->where('referenceable_type', $invoice->getMorphClass())
            ->where('referenceable_id', $invoice->parent_invoice_id)
            ->value('external_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
