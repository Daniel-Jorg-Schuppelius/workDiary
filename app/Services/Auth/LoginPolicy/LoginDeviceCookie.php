<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LoginDeviceCookie.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth\LoginPolicy;

use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Gerätekennung für freigegebene Geräte (Feature 205, MVP-1655).
 *
 * Erkannt wird das Gerät an einem signierten Cookie, nicht an der IP —
 * Heimanschlüsse wechseln die Adresse. Der Wert ist `v1.<Zufall>.<HMAC>`:
 * 32 Zufallsbytes, signiert mit einem aus `APP_KEY` abgeleiteten Schlüssel.
 * Laravel verschlüsselt das Cookie zusätzlich (`EncryptCookies`); die eigene
 * Signatur hält auch dann, wenn jemand den Namen von der Verschlüsselung
 * ausnimmt. Gespeichert wird nur der Hash des Zufallswerts
 * (`user_known_devices.device_hash`), sodass ein Datenbankauszug kein
 * gültiges Cookie liefert.
 */
final class LoginDeviceCookie {
    public const string NAME = 'wd_device';

    private const string VERSION = 'v1';

    /** 400 Tage — die Obergrenze aktueller Browser. */
    private const int LIFETIME_MINUTES = 576000;

    private const string ISSUED_ATTRIBUTE = 'login_policy.device_hash';

    /** Gerätekennung aus einem gültigen Cookie oder aus einem in dieser Anfrage ausgestellten. */
    public static function hashFrom(Request $request): ?string {
        $issued = $request->attributes->get(self::ISSUED_ATTRIBUTE);
        if (is_string($issued)) {
            return $issued;
        }

        $value = $request->cookie(self::NAME);
        if (! is_string($value)) {
            return null;
        }

        $parts = explode('.', $value);
        if (count($parts) !== 3 || $parts[0] !== self::VERSION || preg_match('/^[A-Za-z0-9_-]{43}$/', $parts[1]) !== 1) {
            return null;
        }

        return hash_equals(self::signature($parts[1]), $parts[2]) ? self::hashOf($parts[1]) : null;
    }

    /**
     * Gerätekennung sicherstellen: vorhandenes Cookie nutzen, sonst ein neues
     * ausstellen (wird mit der Antwort gesetzt).
     */
    public static function ensure(Request $request): string {
        $hash = self::hashFrom($request);
        if ($hash !== null) {
            return $hash;
        }

        $token = CryptoHelper::base64UrlEncode(random_bytes(32));
        Cookie::queue(Cookie::make(
            self::NAME,
            self::VERSION . '.' . $token . '.' . self::signature($token),
            self::LIFETIME_MINUTES,
            '/',
            null,
            (bool) config('session.secure', false) || $request->isSecure(),
            true,
            false,
            'lax',
        ));

        $hash = self::hashOf($token);
        $request->attributes->set(self::ISSUED_ATTRIBUTE, $hash);

        return $hash;
    }

    private static function signature(string $token): string {
        return CryptoHelper::hmac('login-device|' . $token, (string) config('app.key'));
    }

    private static function hashOf(string $token): string {
        return (string) CryptoHelper::hash('login-device:' . $token);
    }
}
