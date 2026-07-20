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
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Gemeinsame BIC-Formatregel (Vollaudit 2026-07, M39): 8 oder 11
 * alphanumerische Zeichen — eine Wahrheit statt verstreuter Regex-Kopien.
 */
final class Bic implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/i', trim($value)) !== 1) {
            $fail((string) __('validation.regex'));
        }
    }
}
