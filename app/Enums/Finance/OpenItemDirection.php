<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenItemDirection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Seite eines offenen Postens (Feature 125, MVP-674): Wer schuldet wem?
 */
enum OpenItemDirection: string implements HasLabel {
    use HasOptions;

    /** Forderung — der Kunde schuldet der Organisation. */
    case Receivable = 'receivable';

    /** Verbindlichkeit — die Organisation schuldet. */
    case Payable = 'payable';

    public function label(): string {
        return (string) __('enums.finance.open-item-direction.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Receivable ? 'info' : 'warning';
    }

    /** Auf welcher Seite erhöht sich der Posten? */
    public function increasingSide(): BalanceSide {
        return $this === self::Receivable ? BalanceSide::Debit : BalanceSide::Credit;
    }
}
