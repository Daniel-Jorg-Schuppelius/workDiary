<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenItemStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand eines offenen Postens (Feature 125, MVP-674).
 *
 * `disputed` ist bewusst getrennt von `open`: Ein strittiger Posten ist
 * fachlich offen, gehört aber nicht in dieselbe Mahn- und Liquiditätssicht.
 */
enum OpenItemStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case PartiallySettled = 'partially_settled';
    case Settled = 'settled';
    case Disputed = 'disputed';

    public function label(): string {
        return (string) __('enums.finance.open-item-status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::PartiallySettled => 'info',
            self::Settled => 'success',
            self::Disputed => 'error',
        };
    }

    public function isOpen(): bool {
        return $this !== self::Settled;
    }
}
