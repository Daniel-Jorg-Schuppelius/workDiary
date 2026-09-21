<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Recurr\{Exception, Rule};

/**
 * iCal-RRULE, wie sie der RecurrenceService später expandiert. Ohne diese
 * Prüfung endete Freitext im Feld erst beim Materialisieren in einem 500.
 */
final class RecurrenceRule implements ValidationRule {
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        try {
            new Rule(is_string($value) ? $value : '');
        } catch (Exception) {
            $fail((string) __('Keine gültige Wiederholungsregel (iCal RRULE), z. B. FREQ=WEEKLY;BYDAY=MO.'));
        }
    }
}
