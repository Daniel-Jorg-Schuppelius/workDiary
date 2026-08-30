<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyRoleResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Support;

use App\Models\User;

/**
 * Legacy-Identität und -Adminstatus eines angemeldeten Nutzers.
 *
 * **Ein selbst gesetzter Anzeigename ist keine Identität** (Sicherheitsscan
 * 2026-08-23, S-01). Bis dahin lief hier ein Namens-Fallback: `users.name`
 * bzw. der E-Mail-Localpart wurden gegen eine Liste (`admin,administrator,chef`)
 * verglichen — und beide Felder setzt jeder Nutzer im eigenen Profil ohne
 * Re-Authentifizierung. Wer sich „chef" nannte, war ab dem nächsten Request
 * Org-Admin (`HasAdminBypass` in ~135 Policies). Dieselbe Wurzel steckte in der
 * automatischen Verknüpfung: `resolveLegacyUserId()` suchte das Legacy-Konto
 * über denselben Namen und hängte den Nutzer damit an eine fremde Legacy-ID —
 * bei ID ≤ 3 an ein Legacy-Admin-Konto, sonst an die Tagebücher eines Kollegen.
 *
 * Deshalb gilt jetzt: die Legacy-Verknüpfung entsteht **ausschließlich** beim
 * Login über den eingegebenen Anmeldenamen (dessen Kenntnis das Passwort
 * voraussetzt, {@see \App\Http\Controllers\Auth\LoginController}) oder wird
 * administrativ gesetzt. Hier wird sie nur noch gelesen.
 */
class LegacyRoleResolver {
    /**
     * Verknüpfte Legacy-ID des Nutzers — oder 0, wenn keine besteht.
     *
     * Bewusst ohne Suche: eine Verknüpfung, die aus einem frei wählbaren
     * Namen entsteht, ist keine Verknüpfung, sondern eine Behauptung.
     */
    public static function resolveLegacyUserId(?User $authUser): int {
        if (! $authUser instanceof User) {
            return 0;
        }

        return max(0, (int) ($authUser->legacy_user_id ?? 0));
    }

    /**
     * Legacy-Admin: verknüpfte Legacy-ID ≤ 3.
     *
     * Die Grenze stammt aus dem Altsystem (die ersten drei Konten sind dort
     * die Betreiberkonten).
     */
    public static function isAdminByLegacyId(?User $authUser): bool {
        $legacyUserId = self::resolveLegacyUserId($authUser);

        return $legacyUserId > 0 && $legacyUserId <= 3;
    }

    /** Legacy-Adminstatus. Einzige Quelle ist die verknüpfte Legacy-ID. */
    public static function isAdmin(?User $authUser): bool {
        return self::isAdminByLegacyId($authUser);
    }
}
