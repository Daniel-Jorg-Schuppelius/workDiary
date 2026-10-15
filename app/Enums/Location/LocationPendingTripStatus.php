<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationPendingTripStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Location;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Fahrtvorschlag aus der Spur (MVP-1185): offen, ins Fahrtenbuch übernommen oder verworfen. */
enum LocationPendingTripStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Imported = 'imported';
    case Dismissed = 'dismissed';

    public function label(): string {
        return (string) __('enums.location.location_pending_trip_status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Imported, self::Dismissed],
            self::Imported, self::Dismissed => [],
        };
    }
}
