<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Locales.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

/**
 * Zugriff auf die zentrale Locale-Registry ({@see config/locales.php}).
 *
 * `enabled()` ist die maßgebliche Liste auswählbarer Sprachen: die Schnittmenge
 * aus Registry und der ENV-Whitelist `config('app.available_locales')`. Ist die
 * Whitelist leer, gelten alle Registry-Sprachen.
 */
class Locales {
    /** @return array<string, array{native:string, flag:string, carbon:string}> */
    public static function all(): array {
        /** @var array<string, array{native:string, flag:string, carbon:string}> $registry */
        $registry = (array) config('locales', []);

        return $registry;
    }

    /** @return list<string> Alle in der Registry definierten Codes. */
    public static function codes(): array {
        return array_keys(self::all());
    }

    /**
     * Tatsächlich auswählbare Sprachen (Registry ∩ available_locales),
     * in der Reihenfolge der Registry.
     *
     * @return array<string, array{native:string, flag:string, carbon:string}>
     */
    public static function enabled(): array {
        $whitelist = array_filter((array) config('app.available_locales', []));
        if ($whitelist === []) {
            return self::all();
        }

        return array_intersect_key(self::all(), array_flip($whitelist));
    }

    /** @return list<string> */
    public static function enabledCodes(): array {
        return array_keys(self::enabled());
    }

    public static function isSupported(string $code): bool {
        return array_key_exists($code, self::enabled());
    }

    /**
     * Maßgebliche Auflösung der aktiven Anzeige-Sprache. Reihenfolge:
     *   1. Persönliche Sprache des angemeldeten Benutzers (preferences.locale)
     *   2. Sprache der aktiven Organisation (currentOrganization->locale)
     *   3. Session-Sprache (Gäste-Umschalter)
     *   4. config('app.locale')
     *   5. 'de'
     * Jeweils nur, wenn die Sprache tatsächlich auswählbar ist (isSupported()).
     *
     * Symmetrisch zu {@see \App\Support\Tz::current()}.
     */
    public static function current(): string {
        // 1) Angemeldeter Benutzer
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user instanceof \App\Models\User) {
            $pref = (array) ($user->preferences ?? []);
            $userLocale = is_string($pref['locale'] ?? null) ? $pref['locale'] : null;
            if ($userLocale !== null && self::isSupported($userLocale)) {
                return $userLocale;
            }
        }

        // 2) Aktive Organisation
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof \App\Models\Organization
                && is_string($org->locale) && self::isSupported($org->locale)) {
                return $org->locale;
            }
        }

        // 3) Session (v. a. Gäste)
        $session = request()->hasSession() ? (string) request()->session()->get('locale', '') : '';
        if ($session !== '' && self::isSupported($session)) {
            return $session;
        }

        // 4)/5) Konfig-Fallback
        $configured = (string) config('app.locale', 'de');

        return self::isSupported($configured) ? $configured : 'de';
    }

    /** @return array{native:string, flag:string, carbon:string}|null */
    public static function meta(string $code): ?array {
        return self::all()[$code] ?? null;
    }

    public static function native(string $code): string {
        return self::meta($code)['native'] ?? strtoupper($code);
    }

    public static function flag(string $code): string {
        return self::meta($code)['flag'] ?? '';
    }

    /** Carbon-Locale-Code (Fallback: der Code selbst). */
    public static function carbon(string $code): string {
        return self::meta($code)['carbon'] ?? $code;
    }
}
