<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalReservationKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art eines Belegungsfensters im Verfügbarkeitskalender (MVP-260).
 * Weiche Reservierungen warnen bei Konflikt, harte blockieren.
 */
enum RentalReservationKind: string implements HasLabel {
    use HasOptions;

    case Soft = 'soft';
    case Hard = 'hard';
    case Rental = 'rental';
    case Maintenance = 'maintenance';
    case Cleaning = 'cleaning';
    case Transport = 'transport';

    public function label(): string {
        return match ($this) {
            self::Soft => (string) __('enums.rental.rental_reservation_kind.soft'),
            self::Hard => (string) __('enums.rental.rental_reservation_kind.hard'),
            self::Rental => (string) __('enums.rental.rental_reservation_kind.rental'),
            self::Maintenance => (string) __('enums.rental.rental_reservation_kind.maintenance'),
            self::Cleaning => (string) __('enums.rental.rental_reservation_kind.cleaning'),
            self::Transport => (string) __('enums.rental.rental_reservation_kind.transport'),
        };
    }

    public function isBlocking(): bool {
        return $this !== self::Soft;
    }
}
