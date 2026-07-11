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

/**
 * Kautions-Lebenszyklus (D10: eigener Finanzvorgang, kein Mietumsatz).
 */
enum RentalDepositStatus: string {
    case Requested = 'requested';
    case Received = 'received';
    case Refunded = 'refunded';
    case PartiallyRetained = 'partially_retained';
    case Retained = 'retained';
    case Waived = 'waived';

    public function label(): string {
        return match ($this) {
            self::Requested => (string) __('Angefordert'),
            self::Received => (string) __('Erhalten'),
            self::Refunded => (string) __('Erstattet'),
            self::PartiallyRetained => (string) __('Teilweise einbehalten'),
            self::Retained => (string) __('Einbehalten'),
            self::Waived => (string) __('Verzichtet'),
        };
    }

    public function isSettled(): bool {
        return in_array($this, [self::Refunded, self::PartiallyRetained, self::Retained, self::Waived], true);
    }
}
