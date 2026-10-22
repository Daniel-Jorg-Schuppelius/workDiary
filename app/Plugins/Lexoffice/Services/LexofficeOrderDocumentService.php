<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeOrderDocumentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Entities\ID;
use APIToolkit\Exceptions\ApiException;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use InvalidArgumentException;
use Lexoffice\API\Client;
use Lexoffice\Contracts\Abstracts\API\DocumentEndpointAbstract;
use Lexoffice\Contracts\Abstracts\NamedDocument;
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
 * Anlegen und Zurücklesen gehen über den Beleg-Endpunkt des SDK.
 */
abstract class LexofficeOrderDocumentService {
    /** Lexoffice-Voucher-Endpoint (z. B. `order-confirmations`, `quotations`). */
    abstract protected function endpointPath(): string;

    /** Beleg-Endpunkt des SDK zu {@see endpointPath()}. */
    abstract protected function endpoint(Client $client): DocumentEndpointAbstract;

    /**
     * Beleg-Entität des SDK aus dem Payload.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function document(array $payload): NamedDocument;

    /** ExternalReference-Typ (z. B. `order_confirmation`, `quotation`). */
    abstract public function extType(): string;

    /**
     * Pflichtfelder der Belegart laut Lexware-Referenz, die der Fertigungsauftrag nicht kennt.
     *
     * @return array<string, mixed>
     */
    abstract protected function requiredFields(): array;

    /** Beleg-Titel in Lexoffice. */
    abstract protected function documentTitle(): string;

    /** finance-Übersetzungsschlüssel: Fertigungsauftrag ohne Kunde. */
    abstract protected function noCustomerErrorKey(): string;

    /** finance-Übersetzungsschlüssel: kein Beleg verknüpft. */
    abstract protected function notLinkedErrorKey(): string;

    /**
     * Überträgt den Fertigungsauftrag als Lexoffice-Verkaufsbeleg; ein schon
     * verknüpfter Beleg wird zurückgegeben (`wasRecentlyCreated` = false).
     *
     * @throws RuntimeException Bei fehlender Konfiguration, fehlendem Kunden/
     *                          Kontakt oder API-Fehler.
     */
    public function push(ManufacturingOrder $order): ExternalReference {
        // Ein zweiter Klick legte in Lexoffice einen zweiten Beleg an; der eindeutige Index griff erst danach (MVP-1155).
        $existing = $this->reference($order);
        if ($existing !== null) {
            return $existing;
        }

        $config = LexofficeConfig::resolve($order->organization_id);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_not_configured'));
        }

        $order->loadMissing(['customer', 'article', 'variant']);
        $customer = $order->customer;
        if (! $customer instanceof Customer) {
            throw new RuntimeException((string) __($this->noCustomerErrorKey()));
        }

        $client = app(LexofficeClientFactory::class)->sdk((string) $config['api_key'], $config['base_url'], $config['request_interval']);
        $contactId = app(LexofficeContactLookup::class)->find($customer, $client)
            ?? throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_contact_missing'));
        $payload = $this->buildPayload($order, $contactId, (array) $config['defaults']) + $this->requiredFields();

        try {
            $body = $this->endpoint($client)->create($this->document($payload))->toArray();
        } catch (ApiException $e) {
            throw new RuntimeException(sprintf(
                'Lexoffice %s failed: HTTP %d %s',
                $this->endpointPath(),
                $e->getCode(),
                mb_substr((string) $e->getContent(), 0, 500),
            ), 0, $e);
        } catch (InvalidArgumentException $e) {
            // isValid() der Entität: nichts gesendet; der Controller fängt RuntimeException.
            throw new RuntimeException(sprintf('Lexoffice %s failed: %s', $this->endpointPath(), $e->getMessage()), 0, $e);
        }

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
            throw new RuntimeException((string) __('lexoffice::finance.error.lexoffice_not_configured'));
        }

        $reference = $this->reference($order);
        if ($reference === null) {
            throw new RuntimeException((string) __($this->notLinkedErrorKey()));
        }

        $client = app(LexofficeClientFactory::class)->sdk((string) $config['api_key'], $config['base_url'], $config['request_interval']);
        try {
            return $this->endpoint($client)->get(new ID((string) $reference->external_id))->toArray();
        } catch (ApiException $e) {
            throw new RuntimeException(sprintf('Lexoffice %s fetch failed: HTTP %d', $this->endpointPath(), $e->getCode()), 0, $e);
        }
    }

    /**
     * Die ExternalReference des Belegs zu einem Fertigungsauftrag.
     */
    public function reference(ManufacturingOrder $order): ?ExternalReference {
        return ExternalReference::query()
            ->forPlugin($order->organization_id, LexofficePlugin::ID, $this->extType())
            ->forReferenceable($order)
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
        $quantity = ($order->target_qty?->getValue()->toFloat() ?? 0.0);
        $unit = trim((string) $order->unit) ?: trim((string) $article->base_unit) ?: (string) __('invoicing.unit_piece');

        $netPrice = $variant?->effectiveSalePrice()?->toFloat() ?? 0.0;
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
}
