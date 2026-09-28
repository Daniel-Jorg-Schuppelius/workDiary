<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionTierPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/** Zeitraum, in dem der Umsatz für eine Provisionsstaffel aufläuft (MVP-988). */
enum CommissionTierPeriod: string implements HasLabel {
    use HasOptions;

    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    public function label(): string {
        return (string) __('commission.tier_period.' . $this->value);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function rangeOf(Carbon $day): array {
        return match ($this) {
            self::Month => [$day->copy()->startOfMonth(), $day->copy()->endOfMonth()->startOfDay()],
            self::Quarter => [$day->copy()->startOfQuarter(), $day->copy()->endOfQuarter()->startOfDay()],
            self::Year => [$day->copy()->startOfYear(), $day->copy()->endOfYear()->startOfDay()],
        };
    }
}
