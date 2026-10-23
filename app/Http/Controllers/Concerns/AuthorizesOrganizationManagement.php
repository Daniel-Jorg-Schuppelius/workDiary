<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthorizesOrganizationManagement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Platform\User;

/**
 * Wiederkehrende Schranken der Verwaltungs-Controller. Nur für Controller
 * (setzt `authUser()` des Basis-Controllers voraus).
 */
trait AuthorizesOrganizationManagement {
    /** Org-Admin mit Organisationskontext: sonst 403 bzw. 422. */
    protected function admin(): User {
        $user = $this->authUser();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    /** Nutzer mit Abrechnungsrecht, sonst 403. */
    protected function authorizeBilling(): User {
        $user = $this->authUser();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }
}
