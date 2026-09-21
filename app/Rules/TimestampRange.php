<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimestampRange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use App\Support\Formats;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Datum für eine TIMESTAMP-Spalte (MySQL/MariaDB: 1970-01-01 bis 2038-01-19 UTC).
 * Ein Tag Abstand zu den Grenzen fängt die Zeitzonen-Umrechnung ab. Ohne die
 * Regel scheiterte erst das INSERT (SQLSTATE 22007, UI-Fuzz 2026-09-21).
 */
final class TimestampRange implements ValidationRule {
    private const FIRST_DAY = '1970-01-02';

    private const LAST_DAY = '2038-01-18';

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) || trim($value) === '') {
            return;
        }
        try {
            $day = CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return; // Format meldet die date-Regel.
        }
        if ($day < self::FIRST_DAY || $day > self::LAST_DAY) {
            $fail((string) __('Das Datum muss zwischen :from und :to liegen.', [
                'from' => CarbonImmutable::parse(self::FIRST_DAY)->format(Formats::date()),
                'to' => CarbonImmutable::parse(self::LAST_DAY)->format(Formats::date()),
            ]));
        }
    }
}
