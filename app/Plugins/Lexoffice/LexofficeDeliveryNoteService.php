<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeDeliveryNoteService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\{Customer, ExternalReference, StockDelivery};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Services\Manufacturing\DeliveryService;
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
 * HTTP läuft — analog {@see \App\Services\Finance\Targets\LexofficeTarget} —
 * über {@see PluginApiClient} (php-api-toolkit, mit FakePluginHttp testbar),
 * nicht über das SDK.
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
        $config = LexofficeConfig::resolve($delivery->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.lexoffice_not_configured'));
        }

        $delivery->loadMissing('customer');
        $customer = $delivery->customer;
        if (! $customer instanceof Customer) {
            throw new RuntimeException((string) __('finance.error.lexoffice_delivery_no_customer'));
        }

        $contactId = $this->resolveContactId($customer, $config);
        $payload = $this->buildPayload($delivery, $contactId);

        $response = $this->api($config)->postJson($config['base_url'] . '/delivery-notes', $payload);

        if (! $response->successful()) {
            $this->deliveries->markFacturationResult($delivery, DeliveryFacturationStatus::Failed);
            throw new RuntimeException(sprintf(
                'Lexoffice delivery note failed: HTTP %d %s',
                $response->status(),
                mb_substr((string) $response->body(), 0, 500),
            ));
        }

        $body = (array) ($response->json() ?? []);
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
        $config = LexofficeConfig::resolve($delivery->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.lexoffice_not_configured'));
        }

        $reference = $this->reference($delivery);
        if ($reference === null) {
            throw new RuntimeException((string) __('finance.error.lexoffice_delivery_not_linked'));
        }

        $response = $this->api($config)->getResponse($config['base_url'] . '/delivery-notes/' . $reference->external_id);
        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Lexoffice delivery note fetch failed: HTTP %d', $response->status()));
        }

        return (array) ($response->json() ?? []);
    }

    /**
     * Die ExternalReference des Lexoffice-Lieferscheins zu einer Auslieferung.
     */
    public function reference(StockDelivery $delivery): ?ExternalReference {
        return ExternalReference::query()
            ->forPlugin($delivery->organization_id, LexofficePlugin::ID, self::EXT_TYPE_DELIVERY_NOTE)
            ->forReferenceable($delivery)
            ->first();
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
            'deliveryConditions' => ['deliveryDate' => $deliveredAt->format('Y-m-d\TH:i:s.vP')],
            'title' => (string) __('finance.lexoffice.delivery_title'),
        ];
    }

    /**
     * Kontakt-Auflösung: bestehende ExternalReference → Lexoffice-Kontaktsuche
     * (E-Mail). Ohne Treffer wird der Push mit klarer Meldung abgebrochen
     * (Kontakt zuerst synchronisieren).
     *
     * @param  array{api_key: ?string, base_url: string, defaults: array<string, mixed>}  $config
     */
    private function resolveContactId(Customer $customer, array $config): string {
        $existing = ExternalReference::query()
            ->forPlugin($customer->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($customer)
            ->first();

        if ($existing !== null) {
            return $existing->external_id;
        }

        $email = (string) $customer->email;
        if ($email !== '') {
            $response = $this->api($config)
                ->getResponse($config['base_url'] . '/contacts', ['email' => $email, 'page' => 0, 'size' => 1]);

            if ($response->successful()) {
                $first = ((array) ($response->json('content') ?? []))[0] ?? null;
                if (is_array($first) && ! empty($first['id'])) {
                    ExternalReference::updateOrCreate(
                        [
                            'plugin_id' => LexofficePlugin::ID,
                            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                            'referenceable_type' => $customer->getMorphClass(),
                            'referenceable_id' => $customer->getKey(),
                        ],
                        [
                            'organization_id' => $customer->organization_id,
                            'external_id' => (string) $first['id'],
                            'synced_at' => now(),
                        ],
                    );

                    return (string) $first['id'];
                }
            }
        }

        throw new RuntimeException((string) __('finance.error.lexoffice_contact_missing'));
    }

    /** @param  array{api_key: ?string, base_url: string}  $config */
    private function api(array $config): PluginApiClient {
        $client = app(PluginHttpFactory::class)->client('lexoffice', (string) $config['base_url']);
        $client->setAuthentication(new BearerAuthentication((string) $config['api_key']));

        return $client;
    }
}
