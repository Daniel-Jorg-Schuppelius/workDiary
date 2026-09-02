<?php
/*
 * Created on   : Thu Jul 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyBridge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy;

use App\Legacy\Auth\LegacyUserProvider;
use App\Legacy\Models\{LegacyDiaryEntry, LegacyUser};
use App\Legacy\Support\{LegacyConnectivity, LegacyRoleResolver};
use App\Models\User;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Einziger erlaubter Einstiegspunkt vom neuen Bereich in App\Legacy.
 * Neuer Code referenziert ausschließlich diese Bridge; direkte Zugriffe
 * auf Legacy-Interna meldet scripts/find-legacy-usage-in-new.php.
 * Kapselt nur — kein Rückbau, keine Änderung der Legacy-Semantik.
 */
class LegacyBridge {
    /** Name der Legacy-DB-Verbindung (für DatabaseHealth-Prüfungen). */
    public const CONNECTION = LegacyConnectivity::CONNECTION;

    /** Legacy-Admin-Status (Legacy-ID ≤ 3 oder Fallback-Admin-Liste). */
    public static function isLegacyAdmin(?User $user): bool {
        return LegacyRoleResolver::isAdmin($user);
    }

    /**
     * Best-Effort-Arbeit gegen die Legacy-DB: der Callback läuft nur, wenn die
     * legacy-Verbindung konfiguriert und nicht als down markiert ist; sonst
     * (und bei Verbindungsfehlern) kommt $default zurück.
     */
    public static function attempt(callable $work, mixed $default): mixed {
        return LegacyConnectivity::attempt($work, $default);
    }

    /** Legacy-Konto zur bereits aufgelösten legacy_user_id; null ohne Verknüpfung. */
    public static function userFor(User $user): ?LegacyUser {
        if (! $user->legacy_user_id) {
            return null;
        }

        return LegacyUser::find($user->legacy_user_id);
    }

    /**
     * Legacy-Quelle eines importierten Diary-Eintrags inkl. Autor.
     * Liefert null, wenn keine Verknüpfung besteht, die Legacy-Verbindung
     * nicht konfiguriert oder die Legacy-DB nicht erreichbar ist.
     */
    public static function findDiaryEntryWithAuthor(?int $legacyId): ?LegacyDiaryEntry {
        if (! $legacyId || blank(config('database.connections.legacy.database'))) {
            return null;
        }

        try {
            return LegacyDiaryEntry::with('author:id,uname')->find($legacyId);
        } catch (\Exception) {
            // Legacy nicht erreichbar
            return null;
        }
    }

    /** Auth-Provider „legacy": Login gegen das Altsystem + Schatten-Account-Sync. */
    public static function makeAuthProvider(Hasher $hasher): UserProvider {
        return new LegacyUserProvider($hasher);
    }

    /**
     * SSO-Sperre des Providers für die Dauer von `$work` aussetzen (S-41):
     * der Login-Controller muss das Passwort prüfen dürfen, bevor er über die
     * SSO-Umleitung entscheidet. Eingeloggt wird dabei nicht.
     *
     * @template T
     *
     * @param  callable():T  $work
     * @return T
     */
    public static function ignoringSsoEnforcement(callable $work): mixed {
        return LegacyUserProvider::ignoringSsoEnforcement($work);
    }
}
