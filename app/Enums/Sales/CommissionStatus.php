<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Provisionszeile (Feature 146).
 *
 * `Pending` = berechnet, aber noch keinem geschlossenen Lauf zugeordnet.
 * `Settled` = in einem geschlossenen Lauf festgeschrieben.
 * `Reversed` = durch eine Rueckrechnung (Storno/Gutschrift) neutralisiert;
 * die Zeile selbst bleibt stehen, die Rueckrechnung ist eine eigene Zeile.
 */
enum CommissionStatus: string implements HasLabel {
    use HasOptions;

    case Pending = 'pending';
    case Settled = 'settled';
    case Reversed = 'reversed';

    public function label(): string {
        return match ($this) {
            self::Pending => __('commission.status.pending'),
            self::Settled => __('commission.status.settled'),
            self::Reversed => __('commission.status.reversed'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'warning',
            self::Settled => 'success',
            self::Reversed => 'error',
        };
    }
}
