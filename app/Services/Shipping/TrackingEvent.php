<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrackingEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Shipping;

use Illuminate\Support\Carbon;

/**
 * Ein einzelnes Ereignis des Sendungsverlaufs (Feature 059, MVP-128).
 */
final class TrackingEvent {
    public function __construct(
        public readonly Carbon $occurredAt,
        public readonly string $description,
        public readonly ?string $location = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'description' => $this->description,
            'location' => $this->location,
        ];
    }
}
