<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityPlanRecurrence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Wiederholung einer Planposition der Liquiditätsvorschau (MVP-984). */
enum LiquidityPlanRecurrence: string implements HasLabel {
    use HasOptions;

    case Once = 'once';
    case Monthly = 'monthly';

    public function label(): string {
        return (string) __('enums.finance.liquidity-plan-recurrence.' . $this->value);
    }
}
