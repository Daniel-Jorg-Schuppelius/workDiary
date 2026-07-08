<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentLabel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

/**
 * Ergebnis einer Labelanforderung (Feature 059, MVP-128): Trackingnummer, die
 * carrier-interne Sendungs-ID (für Storno) und das Label als Base64-PDF.
 */
final class ShipmentLabel {
    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $carrierShipmentId,
        public readonly string $labelPdfBase64,
    ) {}
}
