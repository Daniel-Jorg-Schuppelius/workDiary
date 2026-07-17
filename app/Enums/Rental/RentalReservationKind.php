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
            self::Soft => (string) __('Vormerkung'),
            self::Hard => (string) __('Reservierung'),
            self::Rental => (string) __('Verleih'),
            self::Maintenance => (string) __('Wartungsfenster'),
            self::Cleaning => (string) __('Reinigung'),
            self::Transport => (string) __('Transport/Rüstzeit'),
        };
    }

    public function isBlocking(): bool {
        return $this !== self::Soft;
    }
}
