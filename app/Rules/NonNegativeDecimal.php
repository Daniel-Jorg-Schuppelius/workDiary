<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NonNegativeDecimal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nicht negative Zahl in deutscher oder englischer Schreibweise („12,50“,
 * „1.234,5“, „0.125“) für Textfelder mit `inputmode="decimal"`. Gelesen wie
 * später gerechnet wird: {@see NumberHelper::normalizeDecimalStringOrNull()}
 * (auch `Money::ofNullable()`), damit Prüfung und Wert nicht auseinanderlaufen.
 */
final class NonNegativeDecimal implements ValidationRule {
    /** @param numeric-string $max */
    public function __construct(private readonly string $max = '999999999.9999') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        $number = is_string($value) || is_int($value) || is_float($value) ? NumberHelper::normalizeDecimalStringOrNull((string) $value) : null;
        if ($number === null) {
            $fail((string) __('validation.numeric'));

            return;
        }
        if (NumberHelper::isNegativePrecise($number)) {
            $fail((string) __('validation.min.numeric', ['min' => '0']));

            return;
        }
        if (NumberHelper::comparePrecise($number, $this->max, 4) > 0) {
            $fail((string) __('validation.max.numeric', ['max' => $this->max]));
        }
    }
}
