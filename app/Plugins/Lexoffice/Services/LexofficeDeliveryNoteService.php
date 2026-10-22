<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeDeliveryNoteService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\ApiException;
use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Inventory\StockDelivery;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Manufacturing\DeliveryService;
use InvalidArgumentException;
use Lexoffice\API\Endpoints\Documents\DeliveryNotesEndpoint;
use Lexoffice\Entities\Documents\DeliveryNotes\DeliveryNote;
use RuntimeException;

/**
 * Lieferschein-Anbindung an Lexoffice (Feature 045/047, „Lexoffice führt").
 *
 * Push: erzeugt aus einer {@see StockDelivery} (facturation_target=lexoffice)
 * einen Lexoffice-Lieferschein (POST /v1/delivery-notes), legt eine
 * {@see ExternalReference} an und schreibt die zurückgegebene Lexoffice-ID über
 * {@see DeliveryService::markFacturationResult()} zurück (Status HandedOver).
 *
 * Pull: liest den verknüpften Lexoffice-Lieferschein (GET /v1/delivery-notes/{id})
 * zurück — z. B. für Status/Belegnummer.
 *
 * Zurücklesen über den Lieferschein-Endpunkt des SDK. Das Anlegen bleibt roh
 * (auf demselben SDK-Client): die Entität kennt `deliveryConditions` nicht —
 * Lexware laut Referenz auch nicht — und verwürfe das Feld.
 */
class LexofficeDeliveryNoteService {
    public const EXT_TYPE_DELIVERY_NOTE = 'delivery_note';

    public function __construct(private readonly DeliveryService $deliveries) {}

    /**
     * Überträgt die Auslieferung als Lexoffice-Lieferschein.
     *
     * @throws RuntimeException Bei fehlender Konfiguration, fehlendem Kunden/
     *                          Kontakt oder API-Fehler (Auslieferung wird dann
     *                          auf Failed gesetzt).
     */
    public function push(StockDelivery $delivery): ExternalReference {
        $existing = $this->reference($delivery);
        if ($existing !== null) {
            return $existing;
        }

        $client = app(LexofficeClientFactory::class)->for($delivery->organization_id);

        $delivery->loadMissing('customer');
        $customer = $delivery->customer;
        if (! $customer instanceof Customer) {
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_delivery_no_customer'));
        }

        $contactId = app(LexofficeContactLookup::class)->find($customer, $client)
            ?? throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_contact_missing'));
        $payload = $this->buildPayload($delivery, $contactId);

        try {
            $body = (new DeliveryNotesEndpoint($client))->create(DeliveryNote::fromArray($payload))->toArray();
        } catch (ApiException $e) {
            $this->deliveries->markFacturationResult($delivery, DeliveryFacturationStatus::Failed);
            throw new RuntimeException(sprintf(
                'Lexoffice delivery note failed: HTTP %d %s',
                $e->getCode(),
                mb_substr((string) $e->getContent(), 0, 500),
            ), 0, $e);
        } catch (InvalidArgumentException $e) {
            $this->deliveries->markFacturationResult($delivery, DeliveryFacturationStatus::Failed);
            throw new RuntimeException('Lexoffice delivery note failed: ' . $e->getMessage(), 0, $e);
        }

        $externalId = (string) ($body['id'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException('Lexoffice delivery note returned no id.');
        }

        $reference = ExternalReference::create([
            'organization_id' => $delivery->organization_id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => self::EXT_TYPE_DELIVERY_NOTE,
            'referenceable_type' => $delivery->getMorphClass(),
            'referenceable_id' => $delivery->getKey(),
            'external_id' => $externalId,
            'payload' => ['lexoffice_id' => $externalId] + $body + ['_request' => $payload],
            'synced_at' => now(),
        ]);

        $this->deliveries->markFacturationResult($delivery, DeliveryFacturationStatus::HandedOver, $externalId);

        return $reference;
    }

    /**
     * Liest den verknüpften Lexoffice-Lieferschein zurück.
     *
     * @return array<string, mixed>
     */
    public function pull(StockDelivery $delivery): array {
        $client = app(LexofficeClientFactory::class)->for($delivery->organization_id);

        $reference = $this->reference($delivery);
        if ($reference === null) {
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_delivery_not_linked'));
        }

        try {
            return (new DeliveryNotesEndpoint($client))->get(new ID((string) $reference->external_id))->toArray();
        } catch (ApiException $e) {
            throw new RuntimeException(sprintf('Lexoffice delivery note fetch failed: HTTP %d', $e->getCode()), 0, $e);
        }
    }

    /**
     * Die ExternalReference des Lexoffice-Lieferscheins zu einer Auslieferung —
     * auch, wenn ihr Lieferschein-Beleg schon übertragen wurde (MVP-1164).
     */
    public function reference(StockDelivery $delivery): ?ExternalReference {
        $references = ExternalReference::query()->forPlugin($delivery->organization_id, LexofficePlugin::ID, self::EXT_TYPE_DELIVERY_NOTE);
        $note = $delivery->deliveryNoteItem()->first()?->deliveryNote()->first();

        return (clone $references)->forReferenceable($delivery)->first()
            ?? ($note !== null ? (clone $references)->forReferenceable($note)->first() : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(StockDelivery $delivery, string $contactId): array {
        $name = trim((string) $delivery->name_snapshot) ?: (string) __('invoicing.service');
        $sku = trim((string) $delivery->sku_snapshot);
        if ($sku !== '') {
            $name .= ' (' . $sku . ')';
        }

        $deliveredAt = $delivery->delivered_at ?? now();

        return [
            'voucherDate' => $deliveredAt->format('Y-m-d\TH:i:s.vP'),
            'address' => ['contactId' => $contactId],
            'lineItems' => [[
                'type' => 'custom',
                'name' => $name,
                'quantity' => round(($delivery->quantity?->getValue()->toFloat() ?? 0.0), 4),
                'unitName' => trim((string) $delivery->unit) ?: (string) __('invoicing.unit_piece'),
            ]],
            // Lexware kennt am Lieferschein kein `deliveryConditions`; das Lieferdatum trägt `shippingConditions`.
            'shippingConditions' => ['shippingType' => 'delivery', 'shippingDate' => $deliveredAt->format('Y-m-d\TH:i:s.vP')],
            'title' => (string) __('lexoffice::finance.lexoffice.delivery_title'),
        ];
    }
}
