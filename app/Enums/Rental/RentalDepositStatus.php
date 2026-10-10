<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalDepositStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Rental;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kautions-Lebenszyklus (D10: eigener Finanzvorgang, kein Mietumsatz).
 */
enum RentalDepositStatus: string implements HasLabel {
    use HasOptions;

    case Requested = 'requested';
    case Received = 'received';
    case Refunded = 'refunded';
    case PartiallyRetained = 'partially_retained';
    case Retained = 'retained';
    case Waived = 'waived';

    public function label(): string {
        return match ($this) {
            self::Requested => (string) __('enums.rental.rental_deposit_status.requested'),
            self::Received => (string) __('enums.rental.rental_deposit_status.received'),
            self::Refunded => (string) __('enums.rental.rental_deposit_status.refunded'),
            self::PartiallyRetained => (string) __('enums.rental.rental_deposit_status.partially_retained'),
            self::Retained => (string) __('enums.rental.rental_deposit_status.retained'),
            self::Waived => (string) __('enums.rental.rental_deposit_status.waived'),
        };
    }

    public function isSettled(): bool {
        return in_array($this, [self::Refunded, self::PartiallyRetained, self::Retained, self::Waived], true);
    }
}
