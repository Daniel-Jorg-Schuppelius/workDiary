<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettlementKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art eines Ausgleichs am offenen Posten (Feature 125, MVP-674).
 *
 * Teilzahlung, Skonto, Einbehalt und Rückläufer bleiben unterscheidbar — die
 * Summe allein würde später nicht mehr erklären, warum ein Beleg als erledigt
 * gilt, obwohl weniger Geld geflossen ist.
 */
enum SettlementKind: string implements HasLabel {
    use HasOptions;

    case Payment = 'payment';
    case Discount = 'discount';
    case Retention = 'retention';
    case WriteOff = 'write_off';
    case Overpayment = 'overpayment';

    /** Rückbuchung eines früheren Ausgleichs (Rückläufer, aufgehobenes Matching). */
    case Reversal = 'reversal';

    public function label(): string {
        return (string) __('enums.finance.settlement-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Payment => 'success',
            self::Discount, self::Retention => 'info',
            self::WriteOff => 'warning',
            self::Overpayment => 'secondary',
            self::Reversal => 'error',
        };
    }

    /** Öffnet der Ausgleich den Posten wieder, statt ihn zu mindern? */
    public function reopens(): bool {
        return $this === self::Reversal;
    }
}
