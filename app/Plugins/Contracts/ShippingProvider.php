<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShippingProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Models\CarrierConnection;
use App\Services\Shipping\{ShipmentLabel, ShipmentRequest, TrackingResult};

/**
 * Providerneutraler Versand-Vertrag (Feature 059, MVP-128): ein Plugin mit
 * {@see PluginCapability::ShippingProvider} erzeugt/storniert Versandlabels und
 * verfolgt Sendungen bei einem Paketdienst (Referenz: DHL Paket). Die
 * WorkDiary-Domäne ({@see \App\Services\Shipping\ShipmentService}) hängt nur an
 * diesem Vertrag; die carrier-spezifische API kapselt der Adapter.
 */
interface ShippingProvider {
    /** Stabile Carrier-Kennung (z. B. `dhl`) — bindet an {@see CarrierConnection::$carrier}. */
    public function carrier(): string;

    /** Erzeugt ein Versandlabel und liefert Trackingnummer, Carrier-Sendungs-ID und Label-PDF. */
    public function createShipment(CarrierConnection $connection, ShipmentRequest $request): ShipmentLabel;

    /** Storniert eine noch nicht übergebene Sendung; true = erfolgreich storniert. */
    public function cancelShipment(CarrierConnection $connection, string $carrierShipmentId): bool;

    /** Ruft den Sendungsstatus ab und normalisiert ihn auf den WorkDiary-Lebenszyklus. */
    public function track(CarrierConnection $connection, string $trackingNumber): TrackingResult;

    /** Liveness/Auth-Check der Anbindung (für den Plugin-Health). */
    public function healthy(CarrierConnection $connection): bool;
}
