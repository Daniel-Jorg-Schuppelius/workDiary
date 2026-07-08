<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeShippingProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Support;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\CarrierConnection;
use App\Plugins\Contracts\ShippingProvider;
use App\Services\Shipping\{ShipmentLabel, ShipmentRequest, TrackingResult};

/**
 * In-Memory-{@see ShippingProvider} für die ShipmentService-Tests: keine HTTP-
 * Aufrufe, dafür Zähler (Idempotenz) und ein steuerbares Tracking-Ergebnis.
 */
class FakeShippingProvider implements ShippingProvider {
    public int $createCount = 0;

    public int $cancelCount = 0;

    public function __construct(
        private readonly string $carrier = 'mock',
        private TrackingResult $trackingResult = new TrackingResult(ShipmentStatus::InTransit, []),
    ) {}

    public function setTrackingResult(TrackingResult $result): void {
        $this->trackingResult = $result;
    }

    public function carrier(): string {
        return $this->carrier;
    }

    public function createShipment(CarrierConnection $connection, ShipmentRequest $request): ShipmentLabel {
        $this->createCount++;

        // Minimales, gültiges PDF (Base64) — der Service dekodiert und legt es ab.
        $pdf = base64_encode('%PDF-1.4 fake label');

        return new ShipmentLabel('TRACK-' . $this->createCount, 'CARRIER-' . $this->createCount, $pdf);
    }

    public function cancelShipment(CarrierConnection $connection, string $carrierShipmentId): bool {
        $this->cancelCount++;

        return true;
    }

    public function track(CarrierConnection $connection, string $trackingNumber): TrackingResult {
        return $this->trackingResult;
    }

    public function healthy(CarrierConnection $connection): bool {
        return true;
    }
}
