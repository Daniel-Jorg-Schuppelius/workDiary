<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeDocumentIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Enums\Billing\DocumentLineKind;
use App\Enums\Numbering\NumberScope;
use App\Models\Billing\DeliveryNote;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Support\PluginApiClient;
use App\Services\Numbering\Contracts\DocumentIssuer;
use App\Services\Numbering\Dto\IssuedDocument;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lexoffice nummeriert Belege beim Übertragen (Phase 145, F1); Rechnung,
 * Gutschrift und Storno überträgt {@see LexofficeInvoiceService::transfer()}.
 * Der Beleg entsteht festgeschrieben (`finalize=true`), die Nummer kommt per GET zurück,
 * weil Lexoffice beim Anlegen nur die ID liefert. AB und Lieferschein gehen
 * ohne Vorgänger hinüber: Mit Vorgänger legt Lexoffice sie nur als Entwurf an —
 * ohne Nummer, und aus einem Entwurf lässt sich keine Rechnung mehr ableiten.
 */
final class LexofficeDocumentIssuer implements DocumentIssuer {
    /** Kreis => [Referenztyp, Lexoffice-Ressource]. */
    private const RESOURCES = [
        'quote' => [LexofficeQuotationService::EXT_TYPE_QUOTATION, 'quotations'],
        'order_confirmation' => [LexofficeOrderConfirmationService::EXT_TYPE_ORDER_CONFIRMATION, 'order-confirmations'],
        'delivery_note' => [LexofficeDeliveryNoteService::EXT_TYPE_DELIVERY_NOTE, 'delivery-notes'],
    ];

    public function pluginId(): string {
        return LexofficePlugin::ID;
    }

    public function scopes(): array {
        return [NumberScope::Quote, NumberScope::OrderConfirmation, NumberScope::DeliveryNote, NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation];
    }

    public function issue(Model $document, NumberScope $scope): IssuedDocument {
        if ($document instanceof Invoice && in_array($scope, [NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation], true)) {
            $config = LexofficeConfig::resolve((int) $document->organization_id);
            $service = new LexofficeInvoiceService(new LexofficeInvoiceMapper, $config['api_key'], $config['defaults'], $config['base_url'], $config['request_interval']);
            $result = $service->transfer($document);

            return new IssuedDocument($result['external_id'], $result['number']);
        }

        [$extType, $resource] = self::RESOURCES[$scope->value] ?? throw new InvalidArgumentException('Lexoffice issues no ' . $scope->value);
        $payload = match (true) {
            $document instanceof Quote && $scope === NumberScope::Quote => fn (PluginApiClient $api, string $baseUrl, array $defaults): array => $this->quotationPayload($document, $api, $baseUrl, $defaults),
            $document instanceof OrderConfirmation && $scope === NumberScope::OrderConfirmation => fn (PluginApiClient $api, string $baseUrl, array $defaults): array => $this->orderConfirmationPayload($document, $api, $baseUrl, $defaults),
            $document instanceof DeliveryNote && $scope === NumberScope::DeliveryNote => fn (PluginApiClient $api, string $baseUrl, array $defaults): array => $this->deliveryNotePayload($document, $api, $baseUrl, $defaults),
            default => throw new InvalidArgumentException('Lexoffice issues no ' . $scope->value . ' for ' . $document::class),
        };

        $existing = ExternalReference::query()
            ->forPlugin((int) $document->getAttribute('organization_id'), LexofficePlugin::ID, $extType)
            ->forReferenceable($document)
            ->first();
        if ($existing instanceof ExternalReference && (string) $existing->external_number !== '') {
            return new IssuedDocument((string) $existing->external_id, (string) $existing->external_number);
        }

        $config = LexofficeConfig::resolve((int) $document->getAttribute('organization_id'));
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_not_configured'));
        }
        $api = app(LexofficeClientFactory::class)->fromConfig($config);
        $baseUrl = (string) $config['base_url'];

        // Erst die Verknüpfung, dann die Nummer: Scheitert der Abruf, legt der nächste Versuch keinen zweiten Beleg an.
        $existing ??= ExternalReference::create([
            'organization_id' => $document->getAttribute('organization_id'),
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => $extType,
            'referenceable_type' => $document->getMorphClass(),
            'referenceable_id' => $document->getKey(),
            'external_id' => $this->create($api, $baseUrl, $payload($api, $baseUrl, (array) $config['defaults']), $resource),
            'payload' => ['_resource' => $resource],
            'synced_at' => now(),
        ]);
        $number = $this->voucherNumber($api, $baseUrl . '/' . $resource . '/' . $existing->external_id);
        $existing->forceFill(['external_number' => $number, 'synced_at' => now()])->save();

        return new IssuedDocument((string) $existing->external_id, $number);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function quotationPayload(Quote $quote, PluginApiClient $api, string $baseUrl, array $defaults): array {
        $quote->loadMissing(['items', 'customer']);
        $items = $quote->items->sortBy('position')->values();

        return array_filter([
            'voucherDate' => now()->format('Y-m-d\TH:i:s.vP'),
            'expirationDate' => $quote->valid_until?->endOfDay()->format('Y-m-d\TH:i:s.vP'),
            'address' => ['contactId' => $this->contactId($quote->customer, $api, $baseUrl)],
            'lineItems' => LexofficeLineItems::from($items, withOptional: true),
            'totalPrice' => ['currency' => $quote->documentCurrency()->value, 'totalDiscountPercentage' => 0],
            'taxConditions' => ['taxType' => LexofficeLineItems::taxType($items, $defaults)],
            'title' => (string) __('Angebot'),
            'remark' => filled($quote->terms) ? (string) $quote->terms : null,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function orderConfirmationPayload(OrderConfirmation $confirmation, PluginApiClient $api, string $baseUrl, array $defaults): array {
        $confirmation->loadMissing(['items', 'customer']);
        $items = $confirmation->items->sortBy('position')->values();

        return array_filter([
            'voucherDate' => now()->format('Y-m-d\TH:i:s.vP'),
            'address' => ['contactId' => $this->contactId($confirmation->customer, $api, $baseUrl)],
            'lineItems' => LexofficeLineItems::from($items),
            'totalPrice' => ['currency' => $confirmation->documentCurrency()->value, 'totalDiscountPercentage' => 0],
            'taxConditions' => ['taxType' => LexofficeLineItems::taxType($items, $defaults)],
            'shippingConditions' => ['shippingType' => 'none'],
            'title' => (string) __('Auftragsbestätigung'),
            'remark' => filled($confirmation->note) ? (string) $confirmation->note : null,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * Lieferschein ohne Preise; das Lieferdatum steht in den Versandbedingungen.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function deliveryNotePayload(DeliveryNote $note, PluginApiClient $api, string $baseUrl, array $defaults): array {
        $note->loadMissing(['items', 'customer']);
        $lineItems = [];
        foreach ($note->items as $item) {
            $name = trim($item->description) ?: (string) __('Position');
            if ($item->line_kind !== DocumentLineKind::Item) {
                $lineItems[] = ['type' => 'text', 'name' => mb_substr($name, 0, 255)];
                continue;
            }
            $lineItems[] = array_filter([
                'type' => 'custom',
                'name' => mb_substr(filled($item->sku) ? $name . ' (' . $item->sku . ')' : $name, 0, 255),
                'description' => mb_strlen($name) > 255 ? $name : null,
                'quantity' => round((float) $item->quantity, 4),
                'unitName' => $item->unit ?: (string) __('invoicing.unit_piece'),
            ], static fn ($value): bool => $value !== null);
        }

        return array_filter([
            'voucherDate' => now()->format('Y-m-d\TH:i:s.vP'),
            'address' => ['contactId' => $this->contactId($note->customer, $api, $baseUrl)],
            'lineItems' => $lineItems,
            'taxConditions' => ['taxType' => (string) ($defaults['default_tax_type'] ?? 'net')],
            'shippingConditions' => ['shippingType' => 'delivery', 'shippingDate' => ($note->delivered_on ?? now())->copy()->startOfDay()->format('Y-m-d\TH:i:s.vP')],
            'title' => (string) __('manufacturing.delivery_note.title'),
            'remark' => filled($note->note) ? (string) $note->note : null,
        ], static fn ($value): bool => $value !== null);
    }

    private function contactId(?Customer $customer, PluginApiClient $api, string $baseUrl): string {
        if (! $customer instanceof Customer) {
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_document_no_customer'));
        }

        return app(LexofficeContactLookup::class)->find($customer, $api, $baseUrl)
            ?? throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_contact_missing'));
    }

    /** @param array<string, mixed> $payload */
    private function create(PluginApiClient $api, string $baseUrl, array $payload, string $resource): string {
        $response = $api->postJson($baseUrl . '/' . $resource . '?finalize=true', $payload);
        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Lexoffice %s failed: HTTP %d %s', $resource, $response->status(), mb_substr((string) $response->body(), 0, 500)));
        }
        $externalId = (string) ($response->json('id') ?? '');
        if ($externalId === '') {
            throw new RuntimeException(sprintf('Lexoffice %s returned no id.', $resource));
        }

        return $externalId;
    }

    private function voucherNumber(PluginApiClient $api, string $url): string {
        $response = $api->getResponse($url);
        $number = $response->successful() ? (string) ($response->json('voucherNumber') ?? '') : '';
        if ($number === '') {
            throw new RuntimeException((string) __('numbering.error.no_number', ['program' => 'Lexoffice']));
        }

        return $number;
    }
}
