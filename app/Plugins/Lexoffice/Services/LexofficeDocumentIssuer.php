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

use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\ApiException;
use App\Enums\Billing\DocumentLineKind;
use App\Enums\Numbering\NumberScope;
use App\Models\Billing\DeliveryNote;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Services\Invoicing\QuoteService;
use App\Services\Numbering\Contracts\DocumentIssuer;
use App\Services\Numbering\Dto\IssuedDocument;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Lexoffice\API\Client;
use Lexoffice\API\Endpoints\Documents\{DeliveryNotesEndpoint, OrderConfirmationsEndpoint, QuotationsEndpoint};
use Lexoffice\Contracts\Abstracts\API\DocumentEndpointAbstract;
use Lexoffice\Entities\Documents\DeliveryNotes\DeliveryNote as LexofficeDeliveryNote;
use Lexoffice\Entities\Documents\OrderConfirmations\OrderConfirmation as LexofficeOrderConfirmation;
use Lexoffice\Entities\Documents\Quotations\Quotation as LexofficeQuotation;
use RuntimeException;

/**
 * Lexoffice nummeriert Belege beim Übertragen (Phase 145, F1); Rechnung,
 * Gutschrift und Storno überträgt {@see LexofficeInvoiceService::transfer()}.
 * Der Beleg entsteht festgeschrieben (`finalize=true`), die Nummer kommt per GET zurück,
 * weil Lexoffice beim Anlegen nur die ID liefert. AB und Lieferschein gehen
 * ohne Vorgänger hinüber: Mit Vorgänger legt Lexoffice sie nur als Entwurf an —
 * ohne Nummer, und aus einem Entwurf lässt sich keine Rechnung mehr ableiten.
 *
 * Kontaktsuche, Anlegen und Nummernabruf teilen sich einen gedrosselten
 * SDK-Client.
 */
final class LexofficeDocumentIssuer implements DocumentIssuer {
    /** Kreis => [Referenztyp, Lexoffice-Ressource, SDK-Endpunkt, SDK-Entität]. */
    private const RESOURCES = [
        'quote' => [LexofficeQuotationService::EXT_TYPE_QUOTATION, 'quotations', QuotationsEndpoint::class, LexofficeQuotation::class],
        'order_confirmation' => [LexofficeOrderConfirmationService::EXT_TYPE_ORDER_CONFIRMATION, 'order-confirmations', OrderConfirmationsEndpoint::class, LexofficeOrderConfirmation::class],
        'delivery_note' => [LexofficeDeliveryNoteService::EXT_TYPE_DELIVERY_NOTE, 'delivery-notes', DeliveryNotesEndpoint::class, LexofficeDeliveryNote::class],
    ];

    public function pluginId(): string {
        return LexofficePlugin::ID;
    }

    public function scopes(): array {
        return [NumberScope::Quote, NumberScope::OrderConfirmation, NumberScope::DeliveryNote, NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation];
    }

    public function issue(Model $document, NumberScope $scope): IssuedDocument {
        if ($document instanceof Invoice && in_array($scope, [NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation], true)) {
            $result = (new LexofficeInvoiceService((int) $document->organization_id))->transfer($document);

            return new IssuedDocument($result['external_id'], $result['number']);
        }

        [$extType, $resource, $endpointClass, $entityClass] = self::RESOURCES[$scope->value] ?? throw new InvalidArgumentException('Lexoffice issues no ' . $scope->value);
        $payload = match (true) {
            $document instanceof Quote && $scope === NumberScope::Quote => fn (Client $client, array $defaults): array => $this->quotationPayload($document, $client, $defaults),
            $document instanceof OrderConfirmation && $scope === NumberScope::OrderConfirmation => fn (Client $client, array $defaults): array => $this->orderConfirmationPayload($document, $client, $defaults),
            $document instanceof DeliveryNote && $scope === NumberScope::DeliveryNote => fn (Client $client, array $defaults): array => $this->deliveryNotePayload($document, $client, $defaults),
            default => throw new InvalidArgumentException('Lexoffice issues no ' . $scope->value . ' for ' . $document::class),
        };

        $existing = ExternalReference::query()
            ->forPlugin((int) $document->getAttribute('organization_id'), LexofficePlugin::ID, $extType)
            ->forReferenceable($document)
            ->first();
        if ($existing instanceof ExternalReference && (string) $existing->external_number !== '') {
            return new IssuedDocument((string) $existing->external_id, (string) $existing->external_number);
        }

        $client = app(LexofficeClientFactory::class)->for((int) $document->getAttribute('organization_id'));
        $endpoint = new $endpointClass($client);

        // Erst die Verknüpfung, dann die Nummer: Scheitert der Abruf, legt der nächste Versuch keinen zweiten Beleg an.
        $existing ??= ExternalReference::create([
            'organization_id' => $document->getAttribute('organization_id'),
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => $extType,
            'referenceable_type' => $document->getMorphClass(),
            'referenceable_id' => $document->getKey(),
            'external_id' => $this->create($endpoint, $entityClass::fromArray($payload($client, LexofficeConfig::resolve((int) $document->getAttribute('organization_id'))['defaults'])), $resource),
            'payload' => ['_resource' => $resource],
            'synced_at' => now(),
        ]);
        $number = $this->voucherNumber($endpoint, (string) $existing->external_id);
        $existing->forceFill(['external_number' => $number, 'synced_at' => now()])->save();

        return new IssuedDocument((string) $existing->external_id, $number);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function quotationPayload(Quote $quote, Client $client, array $defaults): array {
        $quote->loadMissing(['items', 'customer']);
        $items = $quote->items->sortBy('position')->values();

        return array_filter([
            'voucherDate' => now()->format('Y-m-d\TH:i:s.vP'),
            // Pflicht bei Lexware; ohne „gültig bis“ gilt die Bindefrist der Organisation.
            'expirationDate' => CarbonImmutable::instance($quote->valid_until ?? QuoteService::defaultValidUntil())->endOfDay()->format('Y-m-d\TH:i:s.vP'),
            'address' => ['contactId' => $this->contactId($quote->customer, $client)],
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
    private function orderConfirmationPayload(OrderConfirmation $confirmation, Client $client, array $defaults): array {
        $confirmation->loadMissing(['items', 'customer']);
        $items = $confirmation->items->sortBy('position')->values();

        return array_filter([
            'voucherDate' => now()->format('Y-m-d\TH:i:s.vP'),
            'address' => ['contactId' => $this->contactId($confirmation->customer, $client)],
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
    private function deliveryNotePayload(DeliveryNote $note, Client $client, array $defaults): array {
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
            'address' => ['contactId' => $this->contactId($note->customer, $client)],
            'lineItems' => $lineItems,
            'taxConditions' => ['taxType' => (string) ($defaults['default_tax_type'] ?? 'net')],
            'shippingConditions' => ['shippingType' => 'delivery', 'shippingDate' => ($note->delivered_on ?? now())->copy()->startOfDay()->format('Y-m-d\TH:i:s.vP')],
            'title' => (string) __('manufacturing.delivery_note.title'),
            'remark' => filled($note->note) ? (string) $note->note : null,
        ], static fn ($value): bool => $value !== null);
    }

    private function contactId(?Customer $customer, Client $client): string {
        if (! $customer instanceof Customer) {
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_document_no_customer'));
        }

        return app(LexofficeContactLookup::class)->find($customer, $client)
            ?? throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_contact_missing'));
    }

    private function create(DocumentEndpointAbstract $endpoint, NamedEntityInterface $document, string $resource): string {
        try {
            $externalId = (string) ($endpoint->create($document, null, true)->toArray()['id'] ?? '');
        } catch (ApiException $e) {
            throw new RuntimeException(sprintf('Lexoffice %s failed: HTTP %d %s', $resource, $e->getCode(), mb_substr((string) $e->getContent(), 0, 500)), 0, $e);
        } catch (InvalidArgumentException $e) {
            // isValid() der Entität: Lexware lehnte denselben Beleg ab, die Aufrufer fangen RuntimeException.
            throw new RuntimeException(sprintf('Lexoffice %s failed: %s', $resource, $e->getMessage()), 0, $e);
        }
        if ($externalId === '') {
            throw new RuntimeException(sprintf('Lexoffice %s returned no id.', $resource));
        }

        return $externalId;
    }

    private function voucherNumber(DocumentEndpointAbstract $endpoint, string $externalId): string {
        try {
            $number = (string) ($endpoint->get(new ID($externalId))->toArray()['voucherNumber'] ?? '');
        } catch (ApiException) {
            $number = '';
        }
        if ($number === '') {
            throw new RuntimeException((string) __('numbering.error.no_number', ['program' => 'Lexoffice']));
        }

        return $number;
    }
}
