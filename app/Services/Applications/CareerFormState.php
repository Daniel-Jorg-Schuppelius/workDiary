<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CareerFormState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Signierter Formularzustand für den öffentlichen Bewerbungseingang (MVP-437).
 *
 * Ersetzt die Session-CSRF-Abhängigkeit, damit die **einbettbare** Variante
 * ohne Drittanbieter-Cookies funktioniert: Der Token ist ein per APP_KEY
 * verschlüsseltes `postingId|nonce|issuedAt`. Der `nonce` liefert zugleich die
 * **idempotente Eingangsreferenz** (gleiches Formular → gleiche Referenz →
 * Doppelsendung wird über den Unique-Index abgefangen).
 */
final class CareerFormState {
    /** Gültigkeitsfenster des Formulars (Sekunden). */
    public const TTL = 7200;

    public static function issue(int $postingId, int $issuedAt): string {
        $nonce = Str::random(32);

        return Crypt::encryptString($postingId . '|' . $nonce . '|' . $issuedAt);
    }

    /**
     * Prüft den Token gegen das erwartete Posting und das Zeitfenster und liefert
     * den `nonce` (für die Idempotenz-Referenz) oder null bei Ungültigkeit.
     */
    public static function verify(string $token, int $postingId, int $now): ?string {
        try {
            $plain = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        $parts = explode('|', $plain);
        if (count($parts) !== 3) {
            return null;
        }
        [$tokenPostingId, $nonce, $issuedAt] = $parts;

        if ((int) $tokenPostingId !== $postingId || $nonce === '') {
            return null;
        }
        if ($now - (int) $issuedAt > self::TTL || $now + 60 < (int) $issuedAt) {
            return null;
        }

        return $nonce;
    }
}
