<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsoCountryCode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use CommonToolkit\Enums\CountryCode;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ländercode nach ISO 3166 aus dem Toolkit-Enum. `size:2` ließ „UK“ und „xx“
 * durch (Konsolidierungs-Audit 2026-10, k1-10). Die Schreibweise ist egal —
 * gespeichert wird groß, das besorgt der Aufrufer.
 */
final class IsoCountryCode implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value) || CountryCode::tryFrom(strtoupper($value)) === null) {
            $fail('validation.enum')->translate();
        }
    }
}
