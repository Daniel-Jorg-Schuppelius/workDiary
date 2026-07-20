<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Iban.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Gemeinsame IBAN-Formatregel (Vollaudit 2026-07, M39): eine Wahrheit statt
 * vier heterogener Stellen. Bewusst die LOSE Formatprüfung (Länderkürzel +
 * 10–40 Stellen, Leerzeichen erlaubt) — die strengere Prüfsummen-Validierung
 * (CommonToolkit Validator::isIBAN) wäre eine Verschärfung gegen
 * Bestandsdaten und braucht eine eigene Entscheidung (Beleg M39).
 */
final class Iban implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || preg_match('/^[A-Z]{2}[0-9A-Z\s]{10,40}$/i', $value) !== 1) {
            $fail((string) __('validation.regex'));
        }
    }
}
