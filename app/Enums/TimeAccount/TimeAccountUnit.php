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

/** Einheit eines Zeitkontos (MVP-526). */
enum TimeAccountUnit: string implements HasLabel {
    use HasOptions;

    case Minutes = 'minutes';
    case Days = 'days';
    case Count = 'count';

    public function label(): string {
        return match ($this) {
            self::Minutes => __('Minuten'),
            self::Days    => __('Tage'),
            self::Count   => __('Anzahl'),
        };
    }

    /** Formatiert einen Kontowert in der Konteneinheit. */
    public function format(float $quantity): string {
        if ($this === self::Minutes) {
            $sign = $quantity < 0 ? '-' : '';
            $abs = (int) round(abs($quantity));

            return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
        }

        return \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($quantity, $this === self::Count ? 0 : 2, withThousandsSeparator: true) . ' ' . $this->label();
    }
}
