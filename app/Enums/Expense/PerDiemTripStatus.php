<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Expense;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum PerDiemTripStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('enums.per_diem.trip_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Converted => 'success',
            self::Cancelled => 'ghost',
        };
    }
}
