<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserGroupPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{User, UserGroup};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Zugriffsregeln für Benutzergruppen einer Organisation. Verwaltung darf
 * jeder mit Permission `access.manage`; einzelne Gruppen dürfen
 * grundsätzlich nur Mitglieder derselben Organisation einsehen.
 */
class UserGroupPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->hasEffectivePermission('access.manage');
    }

    public function view(User $user, UserGroup $group): bool {
        return $this->sharesOrganization($user, $group)
            && $user->hasEffectivePermission('access.manage');
    }

    public function create(User $user): bool {
        return $user->hasEffectivePermission('access.manage')
            && $user->organization_id !== null;
    }

    public function update(User $user, UserGroup $group): bool {
        return $this->sharesOrganization($user, $group)
            && $user->hasEffectivePermission('access.manage');
    }

    public function delete(User $user, UserGroup $group): bool {
        if ($group->is_system) {
            return false;
        }

        return $this->sharesOrganization($user, $group)
            && $user->hasEffectivePermission('access.manage');
    }
}
