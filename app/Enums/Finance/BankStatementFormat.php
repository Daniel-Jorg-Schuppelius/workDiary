<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankStatementFormat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Importformat eines Bankauszugs (Feature 045, „Priorität 3: Bankimport"):
 * CAMT.053 ist das bevorzugte Format, MT940 dient als Fallback.
 */
enum BankStatementFormat: string implements HasLabel {
    use HasOptions;

    case Camt053 = 'camt053';
    case Mt940 = 'mt940';

    public function label(): string {
        return (string) __('enums.finance.bank-statement-format.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Camt053 => 'info',
            self::Mt940 => 'neutral',
        };
    }
}
