<?php

/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFacade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Toolkit;

use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Wrapper um CommonToolkit\NumberHelper für Locale-bewusste Zahlen-Operationen.
 */
final class NumberFacade {
    /**
     * Wandelt einen Locale-formatierten String in einen float (Default: DE).
     *
     * Akzeptiert deutsche Dezimal-Notation ("1.234,56") ebenso wie US-Notation.
     */
    public static function parseDecimal(string $value, ?CountryCode $country = null): float {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        return NumberHelper::normalizeDecimal($value, $country ?? CountryCode::Germany);
    }

    public static function toGermanFormat(string|float|int $amount, int $decimals = 2, bool $withThousandsSeparator = true): string {
        return NumberHelper::toGermanFormat($amount, $decimals, $withThousandsSeparator);
    }

    public static function formatCurrency(float|int $amount, CurrencyCode $currency = CurrencyCode::Euro, int $decimals = 2): string {
        return NumberHelper::formatCurrency($amount, $currency, $decimals);
    }

    public static function formatBytes(int|float $bytes, int $precision = 2): string {
        return NumberHelper::formatBytes($bytes, $precision);
    }

    public static function clamp(float $value, float $min, float $max): float {
        return NumberHelper::clamp($value, $min, $max);
    }

    public static function percentage(float $part, float $total): float {
        return NumberHelper::percentage($part, $total);
    }
}
