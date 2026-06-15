<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BalanceCheck.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ergebnis der Saldenkette eines Bankauszugs (Feature 045, „Saldenkette"):
 *   ok       = Eröffnungssaldo + Σ(signierte Umsätze) == Schlusssaldo (±1 ct)
 *   mismatch = Differenz größer als die Toleranz (Warnung anzeigen)
 *   unknown  = Salden nicht vollständig vorhanden (z. B. unvollständiger MT940)
 */
enum BalanceCheck: string implements HasLabel {
    use HasOptions;

    case Ok = 'ok';
    case Mismatch = 'mismatch';
    case Unknown = 'unknown';

    public function label(): string {
        return (string) __('enums.finance.balance-check.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Ok => 'success',
            self::Mismatch => 'error',
            self::Unknown => 'warning',
        };
    }
}
