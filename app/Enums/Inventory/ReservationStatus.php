<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReservationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Lebenszyklus einer Bestandsreservierung (Feature 048, MVP-068).
 */
enum ReservationStatus: string {
    case Active = 'active';         // hält verfügbare Menge
    case Fulfilled = 'fulfilled';   // vollständig in Verbrauch überführt
    case Released = 'released';     // (teilweise) wieder freigegeben
    case Cancelled = 'cancelled';

    public function label(): string {
        return __('inventory.reservation_status.' . $this->value);
    }

    public function isOpen(): bool {
        return $this === self::Active;
    }
}
