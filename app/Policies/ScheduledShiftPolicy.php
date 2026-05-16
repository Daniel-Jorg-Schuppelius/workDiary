<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledShiftPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\ScheduledShift;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

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
        return false;
    }

    public function update(User $user, ScheduledShift $shift): bool {
        return false;
    }

    public function delete(User $user, ScheduledShift $shift): bool {
        return false;
    }

    public function publish(User $user, ScheduledShift $shift): bool {
        return false;
    }

    /**
     * Only the assigned user may confirm their own shift.
     */
    public function confirm(User $user, ScheduledShift $shift): bool {
        return $this->owns($user, $shift);
    }
}
