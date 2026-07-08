<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserSessionInvalidator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Widerruf serverseitiger Sitzungen nach einer Credential-Änderung
 * (Passwort-Reset/-Wechsel) — ASVS V3 / Feature-051-Anforderung: eine
 * erfolgreiche Passwort-Änderung entwertet bestehende Sitzungen und
 * „remember me"-Cookies aller Geräte.
 *
 * Die serverseitige Session-Löschung greift nur beim Datenbank-Treiber
 * (nur dort sind Sitzungen zentral löschbar); die remember_token-Rotation
 * läuft treiberunabhängig und entwertet alle Remember-Cookies.
 */
final class UserSessionInvalidator {
    /** Alle Sitzungen des Nutzers widerrufen (Passwort-Reset: keine bleibt). */
    public function invalidateAll(User $user): void {
        $this->purgeSessions($user, null);
        $this->rotateRememberToken($user);
    }

    /**
     * Alle Sitzungen AUSSER der aktuellen widerrufen (Passwort-Wechsel im
     * eingeloggten Zustand — das eigene Gerät bleibt angemeldet).
     */
    public function invalidateOthers(User $user, ?string $keepSessionId): void {
        $this->purgeSessions($user, $keepSessionId);
        $this->rotateRememberToken($user);
    }

    private function purgeSessions(User $user, ?string $keepSessionId): void {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::table('sessions')->where('user_id', $user->getKey());
        if ($keepSessionId !== null) {
            $query->where('id', '!=', $keepSessionId);
        }
        $query->delete();
    }

    private function rotateRememberToken(User $user): void {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
