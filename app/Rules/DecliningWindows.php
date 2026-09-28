<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DecliningWindows.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use CommonToolkit\Helper\Data\DateHelper;
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Anschaffungsfenster der degressiven AfA (MVP-980, Plattform-Einstellung):
 * Liste aus `from`/`until` (Datum), `factor` (Vielfaches des linearen Satzes,
 * > 0 bis 5) und `cap` (Höchstsatz in %, > 0 bis 50); Fenster überschneiden
 * sich nicht.
 */
final class DecliningWindows implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if ($value === null) {
            return;
        }
        if (! is_array($value) || ! array_is_list($value)) {
            $fail((string) __('accounting.fixed_assets.error.windows_shape'));

            return;
        }
        $ranges = [];
        foreach ($value as $index => $window) {
            $from = is_array($window) ? ($window['from'] ?? null) : null;
            $until = is_array($window) ? ($window['until'] ?? null) : null;
            $factor = is_array($window) ? Decimal::ofNullable(is_scalar($window['factor'] ?? null) ? (string) $window['factor'] : null) : null;
            $cap = is_array($window) ? Decimal::ofNullable(is_scalar($window['cap'] ?? null) ? (string) $window['cap'] : null) : null;
            if (! is_string($from) || ! is_string($until) || ! DateHelper::isValidDate($from, ['Y-m-d']) || ! DateHelper::isValidDate($until, ['Y-m-d']) || $until < $from
                || $factor === null || ! $factor->isPositive() || $factor->greaterThan(Decimal::of(5))
                || $cap === null || ! $cap->isPositive() || $cap->greaterThan(Decimal::of(50))) {
                $fail((string) __('accounting.fixed_assets.error.windows_row', ['row' => $index + 1]));

                return;
            }
            foreach ($ranges as [$otherFrom, $otherUntil]) {
                if ($from <= $otherUntil && $until >= $otherFrom) {
                    $fail((string) __('accounting.fixed_assets.error.windows_overlap', ['row' => $index + 1]));

                    return;
                }
            }
            $ranges[] = [$from, $until];
        }
    }
}
