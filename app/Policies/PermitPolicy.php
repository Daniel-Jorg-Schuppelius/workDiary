<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermitPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Permit, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Genehmigungs-Register. Org-Isolation übernimmt der globale
 * OrganizationScope (Route-Binding findet nur Datensätze der aktiven Org).
 */
class PermitPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::PermitViewAny->value);
    }

    public function view(User $user, Permit $permit): bool {
        return $user->can(P::PermitView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::PermitCreate->value);
    }

    public function update(User $user, Permit $permit): bool {
        return $user->can(P::PermitUpdate->value);
    }

    public function delete(User $user, Permit $permit): bool {
        return $user->can(P::PermitDelete->value);
    }
}
