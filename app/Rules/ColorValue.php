<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ColorValue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Farbwert eines Formularfelds (Sicherheitsaudit 2026-10-04, xi-8): Hex
 * (`#RGB`, `#RRGGBB`, `#RRGGBBAA`) oder ein Ton- bzw. Farbname aus Buchstaben,
 * Ziffern und Bindestrich. Der Wert landet in `style` und `class` —
 * Trennzeichen (`;`, `:`, Klammern, Leerzeichen) sind deshalb ausgeschlossen.
 * Reine Hex-Felder nutzen weiter {@see HexColor}.
 */
final class ColorValue implements ValidationRule {
    private const PATTERN = '/\A(?:#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|[A-Za-z][A-Za-z0-9-]*)\z/';

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail((string) __('validation.regex'));
        }
    }
}
