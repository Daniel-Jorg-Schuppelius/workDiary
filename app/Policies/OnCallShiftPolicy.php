<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallShiftPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{OnCallShift, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class OnCallShiftPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    /**
     * Lesen ist teamweit (Sicherheitsscan 2026-08-23, S-39).
     *
     * Ein Dienstplan lebt davon, dass Kolleginnen und Kollegen ihn sehen — die
     * Weboberfläche zeigt seit je alle Bereitschaften der Organisation samt
     * Namen. `view = owns()` war der Ausreißer, nicht die API: dieselbe Sicht,
     * die im Web offen stand, hätte über die Policy verweigert werden müssen.
     * Vereinheitlicht wird deshalb auf das, was tatsächlich gilt.
     *
     * Ändern bleibt beim Eigentümer.
     */
    public function view(User $user, OnCallShift $shift): bool {
        return $this->sharesOrganization($user, $shift);
    }

    public function update(User $user, OnCallShift $shift): bool {
        return $this->owns($user, $shift);
    }

    public function delete(User $user, OnCallShift $shift): bool {
        return $this->owns($user, $shift);
    }
}
