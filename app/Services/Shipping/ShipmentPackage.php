<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentPackage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

/**
 * Ein Packstück eines Versandauftrags (Feature 059, MVP-128): Gewicht (Gramm)
 * und optionale Maße (cm) — providerneutral.
 */
final class ShipmentPackage {
    public function __construct(
        public readonly int $weightGrams,
        public readonly ?int $lengthCm = null,
        public readonly ?int $widthCm = null,
        public readonly ?int $heightCm = null,
    ) {}
}
