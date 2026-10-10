<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountUnit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\TimeAccount;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Duration;

/** Einheit eines Zeitkontos (MVP-526). */
enum TimeAccountUnit: string implements HasLabel {
    use HasOptions;

    case Minutes = 'minutes';
    case Days = 'days';
    case Count = 'count';

    public function label(): string {
        return match ($this) {
            self::Minutes => __('enums.time_account.time_account_unit.minutes'),
            self::Days    => __('enums.time_account.time_account_unit.days'),
            self::Count   => __('enums.time_account.time_account_unit.count'),
        };
    }

    /** Formatiert einen Kontowert in der Konteneinheit. */
    public function format(float $quantity): string {
        if ($this === self::Minutes) {
            return Duration::ofMinutes((int) round($quantity))->toClock() . ' h';
        }

        return NumberHelper::toGermanFormat($quantity, $this === self::Count ? 0 : 2, withThousandsSeparator: true) . ' ' . $this->label();
    }
}
