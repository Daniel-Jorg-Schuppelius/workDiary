<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FedexPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Fedex;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\{CarrierConnection, Organization, PluginSetting};
use App\Plugins\Contracts\{Plugin, PluginCapability, ShippingProvider};
use App\Plugins\Fedex\Api\FedexApiClient;
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\PluginOrgContext;
use App\Services\Shipping\{CarrierTokenCache, ShipmentLabel, ShipmentRequest, ShipperAddress, TrackingEvent, TrackingResult};
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Versand-/Logistik-Anbindung FedEx (Feature 059, MVP-128 / Bauturbo A5).
 *
 * - Erzeugt und storniert Versandlabels und verfolgt Sendungen über die FedEx
 *   Ship API bzw. Track API (REST, OAuth2 Client-Credentials, Token 60 min).
 * - Kündigt {@see PluginCapability::ShippingProvider} an und ist selbst der
 *   Adapter: der {@see \App\Services\Shipping\ShipmentService} löst über die
 *   {@see \App\Services\Shipping\ShippingProviderRegistry} (Carrier `fedex`) auf.
 * - Pro Organisation konfiguriert ({@see CarrierConnection}: OAuth2-Client-ID/
 *   -Secret verschlüsselt at-rest, Account-Nummer in `billing_number`,
 *   Sandbox-Schalter → `apis-sandbox.fedex.com`).
 * - Label als PDF; Absenderadresse kommt aus den Verkäufer-Stammdaten der
 *   Organisation ({@see ShipperAddress}).
 *
 * Die JSON-Verträge folgen der öffentlichen FedEx-Doku; die Verifikation gegen
 * die echte Sandbox (self-service) und die produktive Label-Zertifizierung
 * durch FedEx stehen aus.
 */
class FedexPlugin implements Plugin, ShippingProvider {
    use PluginDefaults;

    public const ID = 'fedex';

    public const SERVICE_PROVIDER = FedexServiceProvider::class;

    /** Carrier-Schlüssel in `carrier_connections.carrier` und der Registry. */
    public const CARRIER = 'fedex';

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'FedEx';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Erzeugt FedEx-Versandlabels (Ship API, OAuth2 Client-Credentials), storniert Sendungen und verfolgt sie. Pro Organisation mit Client-ID/Secret und Account-Nummer konfiguriert; Label als PDF.');
    }

    public function isEnabled(): bool {
        $org = PluginOrgContext::currentOrNull();
        if ($org instanceof Organization) {
            $row = PluginSetting::forOrganization($org->id, self::ID);
            if ($row->exists) {
                return $row->enabled;
            }
        }

        return (bool) config('plugins.fedex.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::ShippingProvider,
        ];
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.shipments.connections.index',
            'label' => __('Versand'),
            'icon' => 'local_shipping',
        ];
    }

    public function serviceProvider(): ?string {
        return FedexServiceProvider::class;
    }

    /** Per-Org-Konfiguration liegt in `carrier_connections` (Versand-Admin), nicht in plugin_settings. */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    // --- ShippingProvider -------------------------------------------------

    public function carrier(): string {
        return self::CARRIER;
    }

    public function createShipment(CarrierConnection $connection, ShipmentRequest $request): ShipmentLabel {
        $response = $this->client($connection)->createShipment($this->buildShipBody($connection, $request));
        if (! $response->successful()) {
            throw new RuntimeException("FedEx createShipment failed (HTTP {$response->status()}).");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $shipments = is_array($data['output']['transactionShipments'] ?? null) ? $data['output']['transactionShipments'] : [];
        $shipment = is_array($shipments[0] ?? null) ? $shipments[0] : [];

        $tracking = isset($shipment['masterTrackingNumber']) && is_scalar($shipment['masterTrackingNumber'])
            ? (string) $shipment['masterTrackingNumber']
            : '';

        $pieces = is_array($shipment['pieceResponses'] ?? null) ? $shipment['pieceResponses'] : [];
        $piece = is_array($pieces[0] ?? null) ? $pieces[0] : [];
        $documents = is_array($piece['packageDocuments'] ?? null) ? $piece['packageDocuments'] : [];

        $b64 = '';
        foreach ($documents as $document) {
            if (is_array($document) && isset($document['encodedLabel']) && is_string($document['encodedLabel'])) {
                $b64 = $document['encodedLabel'];
                break;
            }
        }

        if ($tracking === '' || $b64 === '') {
            throw new RuntimeException('FedEx createShipment returned no tracking number or label.');
        }

        // Bei FedEx ist die Master-Trackingnummer zugleich der Storno-Schlüssel.
        return new ShipmentLabel($tracking, $tracking, $b64, 'pdf');
    }

    public function cancelShipment(CarrierConnection $connection, string $carrierShipmentId): bool {
        $response = $this->client($connection)->cancelShipment([
            'accountNumber' => ['value' => (string) ($connection->billing_number ?? '')],
            'trackingNumber' => $carrierShipmentId,
        ]);

        if (! $response->successful()) {
            return false;
        }

        return (bool) ($response->json('output.cancelledShipment') ?? false);
    }

    public function track(CarrierConnection $connection, string $trackingNumber): TrackingResult {
        $response = $this->client($connection)->trackShipment($trackingNumber);
        if (! $response->successful()) {
            throw new RuntimeException("FedEx track failed (HTTP {$response->status()}).");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $complete = is_array($data['output']['completeTrackResults'] ?? null) ? $data['output']['completeTrackResults'] : [];
        $results = is_array($complete[0]['trackResults'] ?? null) ? $complete[0]['trackResults'] : [];
        $result = is_array($results[0] ?? null) ? $results[0] : [];

        $statusCode = isset($result['latestStatusDetail']['derivedCode']) && is_string($result['latestStatusDetail']['derivedCode'])
            ? $result['latestStatusDetail']['derivedCode']
            : (isset($result['latestStatusDetail']['code']) && is_string($result['latestStatusDetail']['code'])
                ? $result['latestStatusDetail']['code']
                : 'unknown');

        $rawEvents = is_array($result['scanEvents'] ?? null) ? $result['scanEvents'] : [];
        $events = [];
        foreach ($rawEvents as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $events[] = $this->mapScanEvent($raw);
        }

        return new TrackingResult($this->mapStatus($statusCode), $events);
    }

    public function healthy(CarrierConnection $connection): bool {
        try {
            return $this->client($connection)->ping();
        } catch (Throwable) {
            return false;
        }
    }

    // --- Plugin-Health ----------------------------------------------------

    public function healthCheck(): PluginHealth {
        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        /** @var CarrierConnection|null $connection */
        $connection = CarrierConnection::query()
            ->where('organization_id', $org->id)
            ->where('carrier', self::CARRIER)
            ->first();

        if (! $connection instanceof CarrierConnection) {
            return PluginHealth::degraded(__('Keine FedEx-Anbindung hinterlegt.'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('FedEx-Anbindung ist deaktiviert.'));
        }

        try {
            return $this->healthy($connection)
                ? PluginHealth::ok(__('Verbunden mit der FedEx-API.'))
                : PluginHealth::failing(__('FedEx-API nicht erreichbar oder Zugangsdaten ungültig.'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('FedEx-API-Fehler (:class).', ['class' => class_basename($e)]));
        }
    }

    // --- Mapping ----------------------------------------------------------

    private function client(CarrierConnection $connection): FedexApiClient {
        return new FedexApiClient($connection, app(CarrierTokenCache::class));
    }

    /**
     * VO → FedEx Ship-Request. FedEx verlangt den Shipper-Block inkl. Adresse
     * und die Account-Nummer (`billing_number` der Anbindung); Service-/
     * Abgabeart kommen aus der Plugin-Config (per ENV auf den Vertrag anpassbar).
     *
     * @return array<string, mixed>
     */
    private function buildShipBody(CarrierConnection $connection, ShipmentRequest $request): array {
        $cfg = config('plugins.fedex');
        $recipient = $request->recipient;
        $account = $request->billingNumber ?? ($connection->billing_number ?? '');
        $shipper = ShipperAddress::fromOrganization($connection->organization ?? throw new RuntimeException('Carrier connection has no organization.'));

        $recipientContact = ['personName' => $recipient->contactName ?? $recipient->name];
        if ($recipient->phone !== null) {
            $recipientContact['phoneNumber'] = $recipient->phone;
        }

        $lineItems = [];
        foreach ($request->packages as $package) {
            $item = [
                'weight' => ['units' => 'KG', 'value' => round($package->weightGrams / 1000, 2)],
                'customerReferences' => [
                    ['customerReferenceType' => 'CUSTOMER_REFERENCE', 'value' => $request->reference],
                ],
            ];
            if ($package->lengthCm !== null && $package->widthCm !== null && $package->heightCm !== null) {
                $item['dimensions'] = [
                    'length' => $package->lengthCm,
                    'width' => $package->widthCm,
                    'height' => $package->heightCm,
                    'units' => 'CM',
                ];
            }
            $lineItems[] = $item;
        }

        return [
            'labelResponseOptions' => 'LABEL',
            'accountNumber' => ['value' => $account],
            'requestedShipment' => [
                'shipper' => [
                    'contact' => ['companyName' => $shipper->name],
                    'address' => [
                        'streetLines' => [$shipper->street],
                        'city' => $shipper->city,
                        'postalCode' => $shipper->zip,
                        'countryCode' => $shipper->country,
                    ],
                ],
                'recipients' => [[
                    'contact' => $recipientContact,
                    'address' => [
                        'streetLines' => [$recipient->street],
                        'city' => $recipient->city,
                        'postalCode' => $recipient->zip,
                        'countryCode' => strtoupper($recipient->country),
                    ],
                ]],
                'serviceType' => (string) ($cfg['service_type'] ?? 'FEDEX_INTERNATIONAL_PRIORITY'),
                'packagingType' => 'YOUR_PACKAGING',
                'pickupType' => (string) ($cfg['pickup_type'] ?? 'USE_SCHEDULED_PICKUP'),
                'shippingChargesPayment' => ['paymentType' => 'SENDER'],
                'labelSpecification' => [
                    'imageType' => 'PDF',
                    'labelStockType' => 'PAPER_4X6',
                ],
                'requestedPackageLineItems' => $lineItems,
            ],
        ];
    }

    /** FedEx-Statuscode (derivedCode) → WorkDiary-Lebenszyklus. */
    private function mapStatus(string $statusCode): ShipmentStatus {
        return match (strtoupper($statusCode)) {
            'DL' => ShipmentStatus::Delivered,               // Delivered
            'DE', 'SE', 'RS', 'CA' => ShipmentStatus::Problem, // Delivery/Shipment exception, Return, Cancelled
            'OC', 'IN' => ShipmentStatus::Labeled,           // Order created / Initiated (pre-transit)
            default => ShipmentStatus::InTransit,            // IT/OD/PU/AR/DP/unknown
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function mapScanEvent(array $raw): TrackingEvent {
        $date = isset($raw['date']) && is_string($raw['date']) ? $raw['date'] : null; // ISO 8601
        $occurredAt = $date !== null ? Carbon::parse($date) : Carbon::now();

        $description = isset($raw['eventDescription']) && is_string($raw['eventDescription'])
            ? trim($raw['eventDescription'])
            : '';

        $location = null;
        if (is_array($raw['scanLocation'] ?? null)) {
            $city = $raw['scanLocation']['city'] ?? null;
            $location = is_string($city) && $city !== '' ? $city : null;
        }

        return new TrackingEvent($occurredAt, $description, $location);
    }
}
