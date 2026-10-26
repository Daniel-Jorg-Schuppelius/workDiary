<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalTenancyStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/**
 * Mietverhältnis der Dauervermietung: requested → active | declined | cancelled
 * (Rücknahme im Portal), active → cancelled. Ein Auszug beendet nicht den
 * Status, sondern setzt den letzten Miettag.
 */
enum RentalTenancyStatus: string implements HasLabel, HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Requested = 'requested';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Declined = 'declined';

    public function label(): string {
        return (string) __('enums.rental.rental_tenancy_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Requested => 'info',
            self::Active => 'success',
            self::Cancelled => 'ghost',
            self::Declined => 'error',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Requested => [self::Active, self::Declined, self::Cancelled],
            self::Active => [self::Cancelled],
            self::Cancelled, self::Declined => [],
        };
    }

    /** Noch zu bearbeiten: angefragt oder bestätigt. */
    public function isOpen(): bool {
        return $this === self::Requested || $this === self::Active;
    }
}
