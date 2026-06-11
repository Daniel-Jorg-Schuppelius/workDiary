<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RiskTreatment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Risikobehandlungsoption (vermeiden/vermindern/übertragen/akzeptieren). */
enum RiskTreatment: string implements HasLabel {
    use HasOptions;

    case Avoid = 'avoid';
    case Mitigate = 'mitigate';
    case Transfer = 'transfer';
    case Accept = 'accept';

    public function label(): string {
        return (string) __('enums.isms.risk-treatment.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Avoid => 'error',
            self::Mitigate => 'info',
            self::Transfer => 'secondary',
            self::Accept => 'warning',
        };
    }
}
