<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Schedule;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Schedule\ScheduledShift;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class ScheduledShiftPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, ScheduledShift $shift): bool {
        return true;
    }

    public function create(User $user): bool {
        return $user->can(Permission::ScheduledShiftManage->value);
    }

    public function update(User $user, ScheduledShift $shift): bool {
        return $this->sharesOrganization($user, $shift) && $user->can(Permission::ScheduledShiftManage->value);
    }

    public function delete(User $user, ScheduledShift $shift): bool {
        return $this->update($user, $shift);
    }

    public function publish(User $user, ScheduledShift $shift): bool {
        return $this->update($user, $shift);
    }

    /**
     * Only the assigned user may confirm their own shift.
     */
    public function confirm(User $user, ScheduledShift $shift): bool {
        return $this->owns($user, $shift);
    }
}
