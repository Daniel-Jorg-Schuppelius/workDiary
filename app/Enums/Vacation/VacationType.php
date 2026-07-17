<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Vacation;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum VacationType: string implements HasLabel {
    use HasOptions;

    case Vacation = 'vacation';

    /** @deprecated Krankheit wird ab Mai 2026 über App\Models\SickLeave geführt; Case bleibt für historische Daten. */
    case Sick = 'sick';

    case Special = 'special';
    case Unpaid = 'unpaid';

    public function label(): string {
        return (string) __('enums.vacation.type.' . $this->value);
    }

    /** Zählt dieser Typ gegen den Jahresanspruch (MVP-413)? Sonderurlaub/unbezahlt sind anspruchsneutral. */
    public function countsAgainstEntitlement(): bool {
        return $this === self::Vacation;
    }
}
