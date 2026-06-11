<?php
/*
 * Created on   : Tue Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmailOtpService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\{Cache, Hash, Mail, RateLimiter};

/**
 * E-Mail-Einmalcode als zweiter Faktor. Der Code wird nur GEHASHT im Cache
 * (kurze TTL) abgelegt und per Mail an die hinterlegte Adresse gesendet.
 */
class EmailOtpService {
    private const TTL_SECONDS = 300;        // 5 Minuten Gültigkeit
    private const MAX_VERIFY_ATTEMPTS = 5;  // Fehlversuche, bevor der Code invalidiert wird
    private const RESEND_COOLDOWN = 30;     // Sekunden zwischen zwei Versänden
    private const MAX_PER_HOUR = 5;         // Versände pro Stunde

    private function key(User $user): string {
        return '2fa:email:' . $user->getKey();
    }

    /** Zähler für Fehlversuche – an den USER gebunden (IP-unabhängig). */
    private function attemptsKey(User $user): string {
        return '2fa:email:att:' . $user->getKey();
    }

    private function hourKey(User $user): string {
        return '2fa:email:send:' . $user->getKey();
    }

    private function cooldownKey(User $user): string {
        return '2fa:email:cd:' . $user->getKey();
    }

    /** Erzeugt einen 6-stelligen Code, hasht ihn in den Cache und mailt ihn. */
    public function send(User $user): void {
        // Missbrauch begrenzen: 5/Stunde UND mindestens 30s zwischen zwei Versänden.
        RateLimiter::hit($this->hourKey($user), 3600);
        RateLimiter::hit($this->cooldownKey($user), self::RESEND_COOLDOWN);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($user), Hash::make($code), self::TTL_SECONDS);
        // Fehlversuchszähler für diesen Code zurücksetzen.
        Cache::put($this->attemptsKey($user), 0, self::TTL_SECONDS);

        Mail::to($user->email)->send(new TwoFactorCodeMail($code, (int) ceil(self::TTL_SECONDS / 60)));
    }

    public function canSend(User $user): bool {
        // Stundenlimit nicht erreicht UND 30s-Drossel abgelaufen.
        return RateLimiter::remaining($this->hourKey($user), self::MAX_PER_HOUR) > 0
            && RateLimiter::remaining($this->cooldownKey($user), 1) > 0;
    }

    /**
     * Prueft den eingegebenen Code (Einmalverwendung: bei Treffer geloescht).
     * Brute-Force-Schutz: nach MAX_VERIFY_ATTEMPTS Fehlversuchen wird der Code
     * invalidiert – userbasiert, damit IP-Rotation den Schutz nicht aushebelt.
     */
    public function verify(User $user, string $code): bool {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '') {
            return false;
        }
        /** @var string|null $hash */
        $hash = Cache::get($this->key($user));
        if (! is_string($hash)) {
            return false;
        }
        if (! Hash::check($code, $hash)) {
            $attempts = (int) Cache::get($this->attemptsKey($user), 0) + 1;
            if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
                // Zu viele Fehlversuche: Code verbrennen, neuer Versand nötig.
                Cache::forget($this->key($user));
                Cache::forget($this->attemptsKey($user));
            } else {
                Cache::put($this->attemptsKey($user), $attempts, self::TTL_SECONDS);
            }

            return false;
        }
        Cache::forget($this->key($user));
        Cache::forget($this->attemptsKey($user));

        return true;
    }
}
