<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DesiredShiftPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{DesiredShift, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Wunschdienste sind Self-Service (analog {@see AvailabilityWindowPolicy}):
 * jeder mit availability.manage.own pflegt nur die EIGENEN Wünsche.
 */
class DesiredShiftPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->hasPermissionTo(Permission::AvailabilityManageOwn->value)
            || $user->hasPermissionTo(Permission::StaffingSuggest->value);
    }

    public function view(User $user, DesiredShift $desired): bool {
        return $this->owns($user, $desired)
            || $user->hasPermissionTo(Permission::StaffingSuggest->value) && $this->sharesOrganization($user, $desired);
    }

    public function create(User $user): bool {
        return $user->hasPermissionTo(Permission::AvailabilityManageOwn->value);
    }

    public function update(User $user, DesiredShift $desired): bool {
        return $this->owns($user, $desired)
            && $user->hasPermissionTo(Permission::AvailabilityManageOwn->value);
    }

    public function delete(User $user, DesiredShift $desired): bool {
        return $this->owns($user, $desired)
            && $user->hasPermissionTo(Permission::AvailabilityManageOwn->value);
    }
}
