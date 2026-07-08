<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrackingResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\Shipping\ShipmentStatus;

/**
 * Ergebnis einer Sendungsverfolgung (Feature 059, MVP-128): der auf den
 * WorkDiary-Lebenszyklus normalisierte Status plus der Ereignisverlauf. Der
 * providerspezifische Adapter bildet die Carrier-Status hierauf ab.
 */
final class TrackingResult {
    /**
     * @param  list<TrackingEvent>  $events
     */
    public function __construct(
        public readonly ShipmentStatus $status,
        public readonly array $events = [],
    ) {}
}
