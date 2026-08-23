<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BalanceSide.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Buchungsseite (Feature 125, MVP-672). Trägt die Grundinvariante der
 * doppelten Buchführung: jede Buchung hat beide Seiten in gleicher Höhe.
 */
enum BalanceSide: string implements HasLabel {
    use HasOptions;

    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string {
        return (string) __('enums.finance.balance-side.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Debit ? 'info' : 'accent';
    }

    public function opposite(): self {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
