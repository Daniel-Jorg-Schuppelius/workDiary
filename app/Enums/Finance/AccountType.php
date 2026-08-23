<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kontoart im Kontenplan (Feature 125, MVP-672).
 *
 * Die Kontoart bestimmt die normale Saldenrichtung und die Zuordnung zu
 * Bilanz bzw. Erfolgsrechnung. Sie ist bewusst kontenrahmen-neutral: SKR03,
 * SKR04 oder ein eigener Plan unterscheiden sich in den Nummern, nicht in
 * dieser Systematik.
 */
enum AccountType: string implements HasLabel {
    use HasOptions;

    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string {
        return (string) __('enums.finance.account-type.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Asset => 'info',
            self::Liability => 'warning',
            self::Equity => 'secondary',
            self::Income => 'success',
            self::Expense => 'error',
        };
    }

    /** Vorbelegung der Saldenrichtung; am einzelnen Konto überschreibbar. */
    public function normalBalance(): BalanceSide {
        return match ($this) {
            self::Asset, self::Expense => BalanceSide::Debit,
            self::Liability, self::Equity, self::Income => BalanceSide::Credit,
        };
    }

    /** Bestandskonto (Bilanz) statt Erfolgskonto? */
    public function isBalanceSheet(): bool {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }
}
