<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeOrderDocumentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\{Customer, ExternalReference, ManufacturingOrder};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use RuntimeException;

/**
 * Gemeinsame Basis für Lexoffice-Verkaufsbelege, die aus einem KUNDENBEZOGENEN
 * Fertigungsauftrag ({@see ManufacturingOrder} mit `customer_id`) erzeugt werden:
 * Auftragsbestätigung (order-confirmations) und Angebot (quotations).
 *
 * Beide haben dieselbe Struktur (Position = Artikel × Sollmenge zum
 * Netto-Verkaufspreis) und unterscheiden sich nur in Endpoint, Titel,
 * ExternalReference-Typ und Fehlermeldungen — diese liefern die Subklassen.
 *
 * HTTP über {@see PluginApiClient} (php-api-toolkit, FakePluginHttp-testbar).
 */
abstract class LexofficeOrderDocumentService {
    /** Lexoffice-Voucher-Endpoint (z. B. `order-confirmations`, `quotations`). */
    abstract protected function endpointPath(): string;

    /** ExternalReference-Typ (z. B. `order_confirmation`, `quotation`). */
    abstract public function extType(): string;

    /** Beleg-Titel in Lexoffice. */
    abstract protected function documentTitle(): string;

    /** finance-Übersetzungsschlüssel: Fertigungsauftrag ohne Kunde. */
    abstract protected function noCustomerErrorKey(): string;

    /** finance-Übersetzungsschlüssel: kein Beleg verknüpft. */
    abstract protected function notLinkedErrorKey(): string;

    /**
     * Überträgt den Fertigungsauftrag als Lexoffice-Verkaufsbeleg.
     *
     * @throws RuntimeException Bei fehlender Konfiguration, fehlendem Kunden/
     *                          Kontakt oder API-Fehler.
     */
    public function push(ManufacturingOrder $order): ExternalReference {
        $config = LexofficeConfig::resolve($order->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.lexoffice_not_configured'));
        }

        $order->loadMissing(['customer', 'article', 'variant']);
        $customer = $order->customer;
        if (! $customer instanceof Customer) {
            throw new RuntimeException((string) __($this->noCustomerErrorKey()));
        }

        $contactId = $this->resolveContactId($customer, $config);
        $payload = $this->buildPayload($order, $contactId, (array) $config['defaults']);

        $response = $this->api($config)->postJson($config['base_url'] . '/' . $this->endpointPath(), $payload);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Lexoffice %s failed: HTTP %d %s',
                $this->endpointPath(),
                $response->status(),
                mb_substr((string) $response->body(), 0, 500),
            ));
        }

        $body = (array) ($response->json() ?? []);
        $externalId = (string) ($body['id'] ?? '');
        if ($externalId === '') {
            throw new RuntimeException(sprintf('Lexoffice %s returned no id.', $this->endpointPath()));
        }

        return ExternalReference::create([
            'organization_id' => $order->organization_id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => $this->extType(),
            'referenceable_type' => $order->getMorphClass(),
            'referenceable_id' => $order->getKey(),
            'external_id' => $externalId,
            'payload' => ['lexoffice_id' => $externalId] + $body + ['_request' => $payload],
            'synced_at' => now(),
        ]);
    }

    /**
     * Liest den verknüpften Lexoffice-Beleg zurück.
     *
     * @return array<string, mixed>
     */
    public function pull(ManufacturingOrder $order): array {
        $config = LexofficeConfig::resolve($order->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.lexoffice_not_configured'));
        }

        $reference = $this->reference($order);
        if ($reference === null) {
            throw new RuntimeException((string) __($this->notLinkedErrorKey()));
        }

        $response = $this->api($config)->getResponse($config['base_url'] . '/' . $this->endpointPath() . '/' . $reference->external_id);
        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Lexoffice %s fetch failed: HTTP %d', $this->endpointPath(), $response->status()));
        }

        return (array) ($response->json() ?? []);
    }

    /**
     * Die ExternalReference des Belegs zu einem Fertigungsauftrag.
     */
    public function reference(ManufacturingOrder $order): ?ExternalReference {
        return ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', $this->extType())
            ->where('referenceable_type', $order->getMorphClass())
            ->where('referenceable_id', $order->getKey())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function buildPayload(ManufacturingOrder $order, string $contactId, array $defaults): array {
        $article = $order->article;
        $variant = $order->variant;

        $name = trim((string) $variant?->name) ?: trim((string) $article->name) ?: (string) __('invoicing.service');
        $quantity = (float) $order->target_qty;
        $unit = trim((string) $order->unit) ?: trim((string) $article->base_unit) ?: (string) __('invoicing.unit_piece');

        $netPrice = (float) ($variant?->effectiveSalePrice() ?? 0);
        $currency = (string) ($defaults['default_currency'] ?? 'EUR');
        $vatRate = (float) ($defaults['default_vat_rate'] ?? 19.0);
        $taxType = (string) ($defaults['default_tax_type'] ?? 'net');

        return [
            'voucherDate' => now()->format('Y-m-d\TH:i:s.vP'),
            'address' => ['contactId' => $contactId],
            'lineItems' => [[
                'type' => 'custom',
                'name' => $name,
                'quantity' => round($quantity, 4),
                'unitName' => $unit,
                'unitPrice' => [
                    'currency' => $currency,
                    'netAmount' => round($netPrice, 2),
                    'taxRatePercentage' => $vatRate,
                ],
            ]],
            'totalPrice' => ['currency' => $currency],
            'taxConditions' => ['taxType' => $taxType],
            'title' => $this->documentTitle(),
        ];
    }

    /**
     * Kontakt-Auflösung: bestehende ExternalReference → Lexoffice-Kontaktsuche
     * (E-Mail). Ohne Treffer Abbruch mit klarer Meldung.
     *
     * @param  array{api_key: ?string, base_url: string, defaults: array<string, mixed>}  $config
     */
    private function resolveContactId(Customer $customer, array $config): string {
        $existing = ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
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
