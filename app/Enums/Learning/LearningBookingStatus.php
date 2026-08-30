<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningBookingStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand einer Kursbuchung (Feature 149, MVP-744). Zweiphasig wie die
 * Terminbuchung aus Feature 087: erst die Anfrage, dann die Zusage.
 */
enum LearningBookingStatus: string implements HasLabel {
    use HasOptions;

    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.learning.booking-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Requested => 'warning',
            self::Confirmed => 'success',
            self::Rejected => 'error',
            self::Cancelled => 'ghost',
        };
    }

    public function isOpen(): bool {
        return $this === self::Requested;
    }
}
