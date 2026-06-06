<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Formats.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Maßgebliche Auflösung der aktiven Datums-/Uhrzeit-Anzeigeformate.
 *
 * Reihenfolge (symmetrisch zu {@see Tz} und {@see Locales}):
 *   1. Persönliche Einstellung des Benutzers (preferences.date_format / time_format)
 *   2. Organisation: Setting::get('personalization.date_format' | 'time_format')
 *      (organization.settings → config('personalization.*'))
 *   3. config-Default → harter Fallback
 *
 * Es werden nur kuratierte, flatpickr-kompatible Formate akzeptiert
 * (config('personalization.date_formats' | 'time_formats')); unbekannte Werte
 * fallen auf den Default zurück. So gilt überall – serverseitig (->fdate()) und
 * im Datepicker (altFormat) – dasselbe Format.
 */
final class Formats {
    public const DATE_FALLBACK = 'd.m.Y';
    public const TIME_FALLBACK = 'H:i';

    /** PHP-Datumsformat für die Anzeige (z. B. "d.m.Y"). */
    public static function date(): string {
        return self::resolve('date_format', self::dateOptions(), self::DATE_FALLBACK);
    }

    /** PHP-Uhrzeitformat für die Anzeige (z. B. "H:i"). */
    public static function time(): string {
        return self::resolve('time_format', self::timeOptions(), self::TIME_FALLBACK);
    }

    /** Kombiniertes Datum-+-Uhrzeit-Format. */
    public static function dateTime(): string {
        return self::date() . ' ' . self::time();
    }

    /** @return list<string> */
    public static function dateOptions(): array {
        /** @var list<string> $opts */
        $opts = (array) config('personalization.date_formats', [self::DATE_FALLBACK]);

        return $opts;
    }

    /** @return list<string> */
    public static function timeOptions(): array {
        /** @var list<string> $opts */
        $opts = (array) config('personalization.time_formats', [self::TIME_FALLBACK]);

        return $opts;
    }

    public static function isValidDate(?string $value): bool {
        return $value !== null && in_array($value, self::dateOptions(), true);
    }

    public static function isValidTime(?string $value): bool {
        return $value !== null && in_array($value, self::timeOptions(), true);
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function resolve(string $key, array $allowed, string $fallback): string {
        // 1) User-Preference
        $user = Auth::user();
        if ($user instanceof User) {
            $pref = (array) ($user->preferences ?? []);
            $value = $pref[$key] ?? null;
            if (is_string($value) && in_array($value, $allowed, true)) {
                return $value;
            }
        }

        // 2) Organisation → config-Default
        $value = (string) Setting::get("personalization.$key", $fallback);

        // 3) Fallback, falls ungültig
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
