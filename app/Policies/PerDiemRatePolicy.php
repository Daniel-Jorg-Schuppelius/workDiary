<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRatePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{PerDiemRate, User};

class PerDiemRatePolicy {
    /**
     * Verpflegungspauschalen sind globale, mandantenübergreifende
     * Stammdaten (keine organization_id) — Schreibzugriff ändert die
     * Reisekostenberechnung ALLER Mandanten. Daher nur der Plattform-
     * Betreiber (isGlobalAdmin); ein org-lokaler Admin darf lesen, aber
     * nicht schreiben. Früher lief das über den isAdmin()-Admin-Bypass
     * und war damit für jeden org-lokalen Admin offen (Cross-Tenant).
     */
    public function before(User $user, string $ability): ?bool {
        return $user->isGlobalAdmin() ? true : null;
    }

    public function viewAny(User $user): bool {
        return true; // Lesen für alle (Reisekosten-Berechnung)
    }

    public function view(User $user, PerDiemRate $rate): bool {
        return true;
    }

    public function create(User $user): bool {
        return false;
    }

    public function update(User $user, PerDiemRate $rate): bool {
        return false;
    }

    public function delete(User $user, PerDiemRate $rate): bool {
        return false;
    }
}
