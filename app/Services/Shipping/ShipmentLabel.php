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
 * carrier-interne Sendungs-ID (für Storno) und das Label Base64-kodiert.
 * `format` benennt das Dateiformat wie vom jeweiligen API-Vertrag dokumentiert
 * (DHL/FedEx: PDF; UPS: GIF — die UPS Shipping API liefert kein PDF); der
 * {@see ShipmentService} legt die Datei mit passender Endung/MIME ab.
 */
final class ShipmentLabel {
    /** Erlaubte Label-Formate → MIME-Typ (Ablage als Attachment). */
    public const MIME_BY_FORMAT = [
        'pdf' => 'application/pdf',
        'gif' => 'image/gif',
        'png' => 'image/png',
    ];

    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $carrierShipmentId,
        public readonly string $labelBase64,
        public readonly string $format = 'pdf',
    ) {}

    public function mime(): string {
        return self::MIME_BY_FORMAT[$this->format] ?? 'application/octet-stream';
    }

    public function extension(): string {
        return isset(self::MIME_BY_FORMAT[$this->format]) ? $this->format : 'bin';
    }
}
