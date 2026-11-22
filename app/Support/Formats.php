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

use App\Models\Platform\{Organization, User};
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Duration;
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
    public const string DATE_FALLBACK = 'd.m.Y';
    public const string TIME_FALLBACK = 'H:i';

    /** PHP-Datumsformat für die Anzeige (z. B. "d.m.Y"). */
    public static function date(): string {
        return self::resolve('date_format', self::DATE_FALLBACK);
    }

    /** PHP-Uhrzeitformat für die Anzeige (z. B. "H:i"). */
    public static function time(): string {
        return self::resolve('time_format', self::TIME_FALLBACK);
    }

    /** Kombiniertes Datum-+-Uhrzeit-Format. */
    public static function dateTime(): string {
        return self::date() . ' ' . self::time();
    }

    /**
     * Dauer aus Minuten: 'clock' → "1:30 h", 'decimal' → "1,50 h",
     * 'both' → "1:30 h (1,50 h)". Dezimal nur auf Abrechnungsflächen
     * verwenden; Anwesenheit/Flex bleiben beim Uhrenformat.
     */
    public static function duration(int $minutes, string $mode = 'both', bool $withUnit = true): string {
        $duration = Duration::ofMinutes($minutes);
        $unit = $withUnit ? ' h' : '';
        $clock = $duration->toClock() . $unit;
        $decimal = NumberHelper::toGermanFormat($duration->toDecimalHours(), 2) . $unit;

        return match ($mode) {
            'clock' => $clock,
            'decimal' => $decimal,
            default => $clock . ' (' . $decimal . ')',
        };
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

    /** Aufgelöstes Format; gemerkt je Request unter dem Zustand, von dem es abhängt. */
    private static function resolve(string $key, string $fallback): string {
        $user = Auth::user();
        // Der Schlüssel trägt den Zustand, von dem das Ergebnis abhängt (Rohwerte von Nutzer- und
        // Organisationseinstellung, Config-Vorgabe); Systemeinstellungen entwerten beim Speichern.
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $memo = app(FormatsMemo::class);
        $userAttributes = $user instanceof User ? $user->getAttributes() : [];
        $organizationAttributes = $organization instanceof Organization ? $organization->getAttributes() : [];
        $parts = [
            $userAttributes['id'] ?? null,
            $userAttributes['preferences'] ?? null,
            $organizationAttributes['id'] ?? null,
            $organizationAttributes['settings'] ?? null,
            config("personalization.$key"),
        ];
        // Derselbe Zustand wie beim letzten Aufruf (der Regelfall je Tabellenzelle): ohne Schlüssel zu bauen.
        if (($memo->last[$key][0] ?? null) === $parts) {
            return $memo->last[$key][1];
        }
        $state = $key
            . '|' . ($user instanceof User ? $parts[0] . ':' . self::raw($parts[1]) : '')
            . '|' . ($organization instanceof Organization ? $parts[2] . ':' . self::raw($parts[3]) : '')
            . '|' . self::raw($parts[4]);
        $format = $memo->formats[$state] ??= self::resolveFresh($user, $key, $key === 'date_format' ? self::dateOptions() : self::timeOptions(), $fallback);
        $memo->last[$key] = [$parts, $format];

        return $format;
    }

    private static function raw(mixed $value): string {
        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function resolveFresh(mixed $user, string $key, array $allowed, string $fallback): string {
        // 1) User-Preference
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
