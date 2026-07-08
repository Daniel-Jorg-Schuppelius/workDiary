<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DhlPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dhl;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\{CarrierConnection, Organization, PluginSetting};
use App\Plugins\Contracts\{Plugin, PluginCapability, ShippingProvider};
use App\Plugins\Dhl\Api\DhlApiClient;
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Services\Shipping\{ShipmentLabel, ShipmentRequest, TrackingEvent, TrackingResult};
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Versand-/Logistik-Anbindung DHL Paket (Feature 059, MVP-128).
 *
 * - Erzeugt und storniert Versandlabels und verfolgt Sendungen über die DHL
 *   Parcel DE Shipping API v2 bzw. die „Shipment Tracking – Unified" API.
 * - Kündigt {@see PluginCapability::ShippingProvider} an und ist selbst der
 *   Adapter: der {@see \App\Services\Shipping\ShipmentService} löst über die
 *   {@see \App\Services\Shipping\ShippingProviderRegistry} (Carrier `dhl`) auf.
 * - Pro Organisation konfiguriert ({@see CarrierConnection}: GK-Zugang +
 *   `dhl-api-key` verschlüsselt at-rest, Abrechnungsnummer, Sandbox-Schalter).
 *
 * Der providerneutrale Kern (Idempotenz, Label-Ablage als Attachment,
 * Zustellproblem-Benachrichtigung) ist mocktestbar; dieser Carrier-Adapter
 * bildet die dokumentierten DHL-JSON-Verträge ab und läuft gegen die echte API
 * erst mit freigeschalteten GK-Zugangsdaten.
 */
class DhlPlugin implements Plugin, ShippingProvider {
    use PluginDefaults;

    public const ID = 'dhl';

    public const SERVICE_PROVIDER = DhlServiceProvider::class;

    /** Carrier-Schlüssel in `carrier_connections.carrier` und der Registry. */
    public const CARRIER = 'dhl';

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'DHL Paket';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Erzeugt DHL-Versandlabels, storniert sie und verfolgt Sendungen (Parcel DE Shipping v2 + Shipment Tracking). Pro Organisation mit GK-Zugang und dhl-api-key konfiguriert.');
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

        return (bool) config('plugins.dhl.enabled', false);
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
        return DhlServiceProvider::class;
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
        $response = (new DhlApiClient($connection))->createOrder($this->buildOrderBody($connection, $request));
        if (! $response->successful()) {
            throw new RuntimeException("DHL createOrder failed (HTTP {$response->status()}).");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $item = is_array($items[0] ?? null) ? $items[0] : [];

        $shipmentNo = isset($item['shipmentNo']) && is_scalar($item['shipmentNo']) ? (string) $item['shipmentNo'] : '';
        $label = is_array($item['label'] ?? null) ? $item['label'] : [];
        $b64 = isset($label['b64']) && is_string($label['b64']) ? $label['b64'] : '';

        if ($shipmentNo === '' || $b64 === '') {
            throw new RuntimeException('DHL createOrder returned no shipment number or label.');
        }

        // Bei DHL Paket ist die Sendungsnummer zugleich die Trackingnummer.
        return new ShipmentLabel($shipmentNo, $shipmentNo, $b64);
    }

    public function cancelShipment(CarrierConnection $connection, string $carrierShipmentId): bool {
        return (new DhlApiClient($connection))->deleteOrder($carrierShipmentId)->successful();
    }

    public function track(CarrierConnection $connection, string $trackingNumber): TrackingResult {
        $response = (new DhlApiClient($connection))->trackShipment($trackingNumber);
        if (! $response->successful()) {
            throw new RuntimeException("DHL track failed (HTTP {$response->status()}).");
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $shipments = is_array($data['shipments'] ?? null) ? $data['shipments'] : [];
        $shipment = is_array($shipments[0] ?? null) ? $shipments[0] : [];

        $status = is_array($shipment['status'] ?? null) ? $shipment['status'] : [];
        $statusCode = isset($status['statusCode']) && is_string($status['statusCode']) ? $status['statusCode'] : 'unknown';

        $rawEvents = is_array($shipment['events'] ?? null) ? $shipment['events'] : [];
        $events = [];
        foreach ($rawEvents as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $events[] = $this->mapEvent($raw);
        }

        return new TrackingResult($this->mapStatus($statusCode), $events);
    }

    public function healthy(CarrierConnection $connection): bool {
        try {
            return (new DhlApiClient($connection))->ping();
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
            return PluginHealth::degraded(__('Keine DHL-Anbindung hinterlegt.'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('DHL-Anbindung ist deaktiviert.'));
        }

        try {
            return $this->healthy($connection)
                ? PluginHealth::ok(__('Verbunden mit der DHL-API.'))
                : PluginHealth::failing(__('DHL-API nicht erreichbar oder Zugangsdaten ungültig.'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('DHL-API-Fehler (:class).', ['class' => class_basename($e)]));
        }
    }

    // --- Mapping ----------------------------------------------------------

    /**
     * VO → DHL Parcel DE Shipping v2 Order-Body. Das Absenderprofil zieht DHL
     * aus dem GK-Konto (STANDARD_GRUPPENPROFIL); Empfänger und Gewicht kommen
     * aus dem Versandauftrag.
     *
     * @return array<string, mixed>
     */
    private function buildOrderBody(CarrierConnection $connection, ShipmentRequest $request): array {
        $cfg = config('plugins.dhl');
        $recipient = $request->recipient;

        $firstPackage = $request->packages[0] ?? null;
        $weight = $firstPackage !== null ? $firstPackage->weightGrams : 1000;

        $consignee = [
            'name1' => $recipient->name,
            'addressStreet' => $recipient->street,
            'postalCode' => $recipient->zip,
            'city' => $recipient->city,
            'country' => strtoupper($recipient->country),
        ];
        if ($recipient->email !== null) {
            $consignee['email'] = $recipient->email;
        }
        if ($recipient->phone !== null) {
            $consignee['phone'] = $recipient->phone;
        }

        return [
            'profile' => (string) ($cfg['profile'] ?? 'STANDARD_GRUPPENPROFIL'),
            'shipments' => [[
                'product' => (string) ($cfg['product'] ?? 'V01PAK'),
                'billingNumber' => $request->billingNumber ?? ($connection->billing_number ?? ''),
                'refNo' => $request->reference,
                'consignee' => $consignee,
                'details' => [
                    'weight' => ['uom' => 'g', 'value' => $weight],
                ],
            ]],
        ];
    }

    /** DHL-Unified-Statuscode → WorkDiary-Lebenszyklus. */
    private function mapStatus(string $statusCode): ShipmentStatus {
        return match (strtolower($statusCode)) {
            'delivered' => ShipmentStatus::Delivered,
            'failure' => ShipmentStatus::Problem,
            'pre-transit' => ShipmentStatus::Labeled,
            default => ShipmentStatus::InTransit, // transit|unknown
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function mapEvent(array $raw): TrackingEvent {
        $timestamp = isset($raw['timestamp']) && is_string($raw['timestamp']) ? $raw['timestamp'] : null;
        $occurredAt = $timestamp !== null ? Carbon::parse($timestamp) : Carbon::now();

        $description = isset($raw['description']) && is_string($raw['description']) ? $raw['description'] : '';

        $location = null;
        if (is_array($raw['location'] ?? null) && is_array($raw['location']['address'] ?? null)) {
            $city = $raw['location']['address']['addressLocality'] ?? null;
            $location = is_string($city) ? $city : null;
        }

        return new TrackingEvent($occurredAt, $description, $location);
    }
}
