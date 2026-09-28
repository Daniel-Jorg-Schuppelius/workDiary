<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PasswordResetLinkSender.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Platform\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

/**
 * Reset-Link erzeugen und zustellen — für „Passwort vergessen“ und die
 * Kontosicherung nach einer Übernahme (MVP-1008). Eigene Token-Verwaltung,
 * weil der Login über den Legacy-Provider läuft.
 */
final class PasswordResetLinkSender {
    public function expireMinutes(): int {
        return (int) config('auth.passwords.users.expire', 60);
    }

    public function send(User $user): void {
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );
        $user->notify(new PasswordResetLink($this->resetUrl($token, (string) $user->email), $this->expireMinutes()));
    }

    /**
     * Reset-Link aus der **konfigurierten** Adresse bauen, nicht aus dem
     * Host-Header.
     *
     * `route()` bildet die Wurzel aus `Request::root()` — und die kommt vom
     * Host-Header bzw. bei `TRUSTED_PROXIES=*` sogar aus `X-Forwarded-Host`.
     * Der Aufruf ist unauthentifiziert: ein Angreifer mit der E-Mail-Adresse
     * des Opfers konnte damit eine **echte** Reset-Mail auslösen, deren Link
     * auf seinen eigenen Server zeigt (Sicherheitsscan 2026-08-23, S-11).
     * Klickt das Opfer, hat er Token und Adresse.
     *
     * Dieselbe Härtung, die {@see WebAuthnService} schon hat: `app.url`
     * gewinnt, solange sie gesetzt und nicht die lokale Entwicklungsadresse ist.
     */
    private function resetUrl(string $token, string $email): string {
        $path = route('password.reset', ['token' => $token], absolute: false);
        $configured = rtrim((string) config('app.url', ''), '/');
        $host = parse_url($configured, PHP_URL_HOST);

        $base = is_string($host) && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true)
            ? $configured
            : rtrim(url('/'), '/');

        return $base . $path . '?email=' . urlencode($email);
    }
}
