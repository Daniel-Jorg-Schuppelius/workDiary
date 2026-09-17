<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecentAuthentication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Http\Request;

/**
 * Zeitstempel der letzten vollwertigen Anmeldung in der Sitzung
 * (Sicherheitsaudit 2026-09-17, authflow-1). Wer eine fremde, offene Sitzung
 * kapert, soll daraus kein dauerhaftes Anmeldemittel machen können: die
 * Registrierung eines Passkeys, ein API-Token und der E-Mail-Wechsel
 * verlangen eine frische Anmeldung bzw. das Passwort.
 *
 * Schlüssel und Zeitfenster sind bewusst die von Laravel
 * ({@see \Illuminate\Auth\Middleware\RequirePassword}, `auth.password_timeout`).
 */
final class RecentAuthentication {
    public const SESSION_KEY = 'auth.password_confirmed_at';

    /** Sekunden, die eine Anmeldung/Bestätigung als „frisch" gilt. */
    public static function timeout(): int {
        return (int) config('auth.password_timeout', 900);
    }

    /** Nach Login oder Passwortbestätigung: Sitzung als frisch markieren. */
    public static function confirm(Request $request): void {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, time());
        }
    }

    public static function isRecent(Request $request): bool {
        if (! $request->hasSession()) {
            return false;
        }

        $at = $request->session()->get(self::SESSION_KEY);

        return is_numeric($at) && (time() - (int) $at) < self::timeout();
    }

    /** Vergessen, z. B. wenn ein Bestätigungsversuch scheitert. */
    public static function forget(Request $request): void {
        if ($request->hasSession()) {
            $request->session()->forget(self::SESSION_KEY);
        }
    }
}
