<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpsPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Ups;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\{CarrierConnection, Organization, PluginSetting};
use App\Plugins\Contracts\{Plugin, PluginCapability, ShippingProvider};
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Ups\Api\UpsApiClient;
use App\Services\Shipping\{CarrierTokenCache, ShipmentLabel, ShipmentRequest, ShipperAddress, TrackingEvent, TrackingResult};
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Versand-/Logistik-Anbindung UPS (Feature 059, MVP-128 / Bauturbo A5).
 *
 * - Erzeugt und storniert Versandlabels und verfolgt Sendungen über die UPS
 *   Shipping API (REST, OAuth2 Client-Credentials) bzw. die Track API.
 * - Kündigt {@see PluginCapability::ShippingProvider} an und ist selbst der
 *   Adapter: der {@see \App\Services\Shipping\ShipmentService} löst über die
 *   {@see \App\Services\Shipping\ShippingProviderRegistry} (Carrier `ups`) auf.
 * - Pro Organisation konfiguriert ({@see CarrierConnection}: OAuth2-Client-ID/
 *   -Secret verschlüsselt at-rest, Shipper-Nummer in `billing_number`,
 *   Sandbox-Schalter → CIE `wwwcie.ups.com`).
 * - Label liefert UPS als GIF (kein PDF); Absenderadresse kommt aus den
 *   Verkäufer-Stammdaten der Organisation ({@see ShipperAddress}).
 *
 * Die JSON-Verträge folgen der öffentlichen UPS-Doku; die Verifikation gegen
 * die echte Sandbox (self-service Developer-Account) steht aus.
 */
class UpsPlugin implements Plugin, ShippingProvider {
    use PluginDefaults;

    public const ID = 'ups';

    public const SERVICE_PROVIDER = UpsServiceProvider::class;

    /** Carrier-Schlüssel in `carrier_connections.carrier` und der Registry. */
    public const CARRIER = 'ups';

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'UPS';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Erzeugt UPS-Versandlabels (Shipping API, OAuth2 Client-Credentials), storniert Sendungen und verfolgt sie. Pro Organisation mit Client-ID/Secret und Shipper-Nummer konfiguriert; Label als GIF.');
    }

    public function isEnabled(): bool {
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                $row = PluginSetting::forOrganization($org->id, self::ID);
                if ($row->exists) {
                    return $row->enabled;
                }
            }
        }

        return (bool) config('plugins.ups.enabled', false);
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
        return UpsServiceProvider::class;
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
            throw new RuntimeException("UPS createShipment failed (HTTP {$response->status()}).");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $results = is_array($data['ShipmentResponse']['ShipmentResults'] ?? null) ? $data['ShipmentResponse']['ShipmentResults'] : [];

        $shipmentNo = isset($results['ShipmentIdentificationNumber']) && is_scalar($results['ShipmentIdentificationNumber'])
            ? (string) $results['ShipmentIdentificationNumber']
            : '';

        // UPS liefert PackageResults als Objekt (ein Packstück) ODER Liste.
        $packageResults = is_array($results['PackageResults'] ?? null) ? $results['PackageResults'] : [];
        if ($packageResults !== [] && ! array_is_list($packageResults)) {
            $packageResults = [$packageResults];
        }
        $first = is_array($packageResults[0] ?? null) ? $packageResults[0] : [];

        $tracking = isset($first['TrackingNumber']) && is_scalar($first['TrackingNumber']) ? (string) $first['TrackingNumber'] : $shipmentNo;
        $label = is_array($first['ShippingLabel'] ?? null) ? $first['ShippingLabel'] : [];
        $b64 = isset($label['GraphicImage']) && is_string($label['GraphicImage']) ? $label['GraphicImage'] : '';

        if ($shipmentNo === '' || $b64 === '') {
            throw new RuntimeException('UPS createShipment returned no shipment number or label.');
        }

        return new ShipmentLabel($tracking, $shipmentNo, $b64, 'gif');
    }

    public function cancelShipment(CarrierConnection $connection, string $carrierShipmentId): bool {
        return $this->client($connection)->voidShipment($carrierShipmentId)->successful();
    }

    public function track(CarrierConnection $connection, string $trackingNumber): TrackingResult {
        $response = $this->client($connection)->trackShipment($trackingNumber);
        if (! $response->successful()) {
            throw new RuntimeException("UPS track failed (HTTP {$response->status()}).");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $shipments = is_array($data['trackResponse']['shipment'] ?? null) ? $data['trackResponse']['shipment'] : [];
        $packages = is_array($shipments[0]['package'] ?? null) ? $shipments[0]['package'] : [];
        $package = is_array($packages[0] ?? null) ? $packages[0] : [];

        // activity[0] ist bei UPS das jüngste Ereignis.
        $rawActivities = is_array($package['activity'] ?? null) ? $package['activity'] : [];
        $events = [];
        $statusType = 'unknown';
        foreach ($rawActivities as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }
            if ($index === 0) {
                $statusType = isset($raw['status']['type']) && is_string($raw['status']['type']) ? $raw['status']['type'] : 'unknown';
            }
            $events[] = $this->mapActivity($raw);
        }

        return new TrackingResult($this->mapStatus($statusType), $events);
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
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        /** @var CarrierConnection|null $connection */
        $connection = CarrierConnection::query()
            ->where('organization_id', $org->id)
            ->where('carrier', self::CARRIER)
            ->first();

        if (! $connection instanceof CarrierConnection) {
            return PluginHealth::degraded(__('Keine UPS-Anbindung hinterlegt.'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('UPS-Anbindung ist deaktiviert.'));
        }

        try {
            return $this->healthy($connection)
                ? PluginHealth::ok(__('Verbunden mit der UPS-API.'))
                : PluginHealth::failing(__('UPS-API nicht erreichbar oder Zugangsdaten ungültig.'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('UPS-API-Fehler (:class).', ['class' => class_basename($e)]));
        }
    }

    // --- Mapping ----------------------------------------------------------

    private function client(CarrierConnection $connection): UpsApiClient {
        return new UpsApiClient($connection, app(CarrierTokenCache::class));
    }

    /**
     * VO → UPS Shipping-Request. Anders als bei DHL (GK-Absenderprofil)
     * verlangt UPS den Shipper-Block inkl. Adresse und Shipper-Nummer
     * (Kontonummer, `billing_number` der Anbindung).
     *
     * @return array<string, mixed>
     */
    private function buildShipBody(CarrierConnection $connection, ShipmentRequest $request): array {
        $cfg = config('plugins.ups');
        $recipient = $request->recipient;
        $account = $request->billingNumber ?? ($connection->billing_number ?? '');
        $shipper = ShipperAddress::fromOrganization($connection->organization ?? throw new RuntimeException('Carrier connection has no organization.'));

        $shipTo = [
            'Name' => $recipient->name,
            'Address' => [
                'AddressLine' => [$recipient->street],
                'City' => $recipient->city,
                'PostalCode' => $recipient->zip,
                'CountryCode' => strtoupper($recipient->country),
            ],
        ];
        if ($recipient->contactName !== null) {
            $shipTo['AttentionName'] = $recipient->contactName;
        }
        if ($recipient->phone !== null) {
            $shipTo['Phone'] = ['Number' => $recipient->phone];
        }

        $packages = [];
        foreach ($request->packages as $package) {
            $entry = [
                'Packaging' => ['Code' => '02'], // Customer Supplied Package
                'PackageWeight' => [
                    'UnitOfMeasurement' => ['Code' => 'KGS'],
                    'Weight' => number_format($package->weightGrams / 1000, 1, '.', ''),
                ],
            ];
            if ($package->lengthCm !== null && $package->widthCm !== null && $package->heightCm !== null) {
                $entry['Dimensions'] = [
                    'UnitOfMeasurement' => ['Code' => 'CM'],
                    'Length' => (string) $package->lengthCm,
                    'Width' => (string) $package->widthCm,
                    'Height' => (string) $package->heightCm,
                ];
            }
            $packages[] = $entry;
        }

        return [
            'ShipmentRequest' => [
                'Request' => ['RequestOption' => 'nonvalidate'],
                'Shipment' => [
                    'Description' => $request->reference,
                    'Shipper' => [
                        'Name' => $shipper->name,
                        'ShipperNumber' => $account,
                        'Address' => [
                            'AddressLine' => [$shipper->street],
                            'City' => $shipper->city,
                            'PostalCode' => $shipper->zip,
                            'CountryCode' => $shipper->country,
                        ],
                    ],
                    'ShipTo' => $shipTo,
                    'PaymentInformation' => [
                        'ShipmentCharge' => ['Type' => '01', 'BillShipper' => ['AccountNumber' => $account]],
                    ],
                    'Service' => ['Code' => (string) ($cfg['service'] ?? '11')],
                    'ReferenceNumber' => ['Value' => $request->reference],
                    'Package' => $packages,
                ],
                'LabelSpecification' => [
                    'LabelImageFormat' => ['Code' => 'GIF'], // UPS liefert kein PDF
                ],
            ],
        ];
    }

    /** UPS-Aktivitäts-Statustyp → WorkDiary-Lebenszyklus. */
    private function mapStatus(string $statusType): ShipmentStatus {
        return match (strtoupper($statusType)) {
            'D' => ShipmentStatus::Delivered,           // Delivered
            'X', 'RS' => ShipmentStatus::Problem,       // Exception / Returned to Shipper
            'M', 'MV' => ShipmentStatus::Labeled,       // Billing Information Received (pre-transit)
            default => ShipmentStatus::InTransit,       // I/P/O/unknown
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function mapActivity(array $raw): TrackingEvent {
        $date = isset($raw['date']) && is_string($raw['date']) ? $raw['date'] : null;       // YYYYMMDD
        $time = isset($raw['time']) && is_string($raw['time']) ? $raw['time'] : '000000';   // HHMMSS
        $occurredAt = $date !== null
            ? Carbon::createFromFormat('YmdHis', $date . str_pad($time, 6, '0')) ?: Carbon::now()
            : Carbon::now();

        $description = isset($raw['status']['description']) && is_string($raw['status']['description'])
            ? trim($raw['status']['description'])
            : '';

        $location = null;
        if (is_array($raw['location']['address'] ?? null)) {
            $city = $raw['location']['address']['city'] ?? null;
            $location = is_string($city) && $city !== '' ? $city : null;
        }

        return new TrackingEvent($occurredAt, $description, $location);
    }
}
