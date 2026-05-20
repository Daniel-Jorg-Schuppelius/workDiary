<?php

/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Vacation;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum VacationStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.vacation.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Approved => 'success',
            self::Rejected => 'error',
            self::Cancelled => 'ghost',
            self::Pending => 'warning',
        };
    }
}
