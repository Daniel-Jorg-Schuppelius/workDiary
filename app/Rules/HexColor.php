<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HexColor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use CommonToolkit\Helper\Data\ColorHelper;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Gemeinsame Hex-Farb-Regel (Vollaudit 2026-07, N49): optionale Raute +
 * 6 Hex-Zeichen — akzeptiert genau das, was ColorHelper::normalizeHex
 * (Branding-Standard) normalisieren kann. Die max:16-Bestandsfelder werden
 * bewusst separat migriert (Verschärfung gegen Bestandsdaten).
 */
final class HexColor implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || ColorHelper::normalizeHex($value) === null) {
            $fail((string) __('validation.regex'));
        }
    }
}
