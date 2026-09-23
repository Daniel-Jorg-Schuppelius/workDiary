<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AvailabilityWindowPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{AvailabilityWindow, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Verfügbarkeitsfenster sind Self-Service: jeder Mitarbeiter mit
 * availability.manage.own pflegt ausschließlich die EIGENEN Fenster.
 * Planer/Teamleitung sehen sie (lesend) über die Planungsansicht.
 */
class AvailabilityWindowPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->hasPermissionTo(Permission::AvailabilityManageOwn->value)
            || $user->hasPermissionTo(Permission::StaffingSuggest->value);
    }

    public function view(User $user, AvailabilityWindow $window): bool {
        return $this->owns($user, $window)
            || $user->hasPermissionTo(Permission::StaffingSuggest->value) && $this->sharesOrganization($user, $window);
    }

    public function create(User $user): bool {
        return $user->hasPermissionTo(Permission::AvailabilityManageOwn->value);
    }

    public function update(User $user, AvailabilityWindow $window): bool {
        return $this->owns($user, $window)
            && $user->hasPermissionTo(Permission::AvailabilityManageOwn->value);
    }

    public function delete(User $user, AvailabilityWindow $window): bool {
        return $this->owns($user, $window)
            && $user->hasPermissionTo(Permission::AvailabilityManageOwn->value);
    }
}
