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
use CommonToolkit\Helper\Data\BankHelper;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Gemeinsame IBAN-Regel: Länderlänge und Prüfziffer (mod 97), Leerzeichen und
 * Kleinschreibung erlaubt. Strikt, damit ein Zahlendreher bei der Eingabe
 * auffällt und nicht erst bei Lohnzahlung oder Lastschrift. Ungültige
 * Bestandsdaten meldet der IdentifierIssueDetector.
 */
final class Iban implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || ! BankHelper::checkIBAN(BankHelper::normalizeIBAN($value) ?? '')) {
            $fail('validation.iban')->translate();
        }
    }
}
