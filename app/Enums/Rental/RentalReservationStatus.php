<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalReservationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand eines Belegungsfensters (MVP-260): nur aktive Fenster belegen den
 * Kalender. Ohne Übergangstabelle: die Akte beendet und storniert ihre
 * aktiven Fenster über die Abfrage, das Storno im Kalender lässt nur aktive
 * Fenster ohne Akte zu.
 */
enum RentalReservationStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
