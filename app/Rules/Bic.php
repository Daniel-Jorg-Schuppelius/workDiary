<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Bic.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use CommonToolkit\Helper\Data\BankHelper;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Gemeinsame BIC-Formatregel (Vollaudit 2026-07, M39): 8 oder 11 Zeichen —
 * eine Wahrheit statt verstreuter Regex-Kopien. Seit Vollscan 2026-08-23
 * (C18) über {@see BankHelper::hasBICFormat()}: Bank- und Ländercode (Position
 * 1–6) müssen Buchstaben sein — rein numerische „BICs" fielen vorher durch die
 * lose App-Regex und lesen über den strikten {@see \App\Casts\BicCast} als null.
 */
final class Bic implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || ! BankHelper::hasBICFormat(trim($value))) {
            $fail((string) __('validation.regex'));
        }
    }
}
