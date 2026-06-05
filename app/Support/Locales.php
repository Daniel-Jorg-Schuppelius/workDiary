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
