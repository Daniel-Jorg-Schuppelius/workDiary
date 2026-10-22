<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Time;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Time\Timesheet;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class TimesheetPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    /** Eigene Zettel sieht jeder; die Team-Sicht der Liste hängt an timesheet.viewAny. */
    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Timesheet $timesheet): bool {
        if ($this->owns($user, $timesheet, 'user_id')) {
            return true;
        }

        // Fremde Stundenzettel lesen; Ändern, Signieren und Löschen bleiben beim Eigentümer.
        return $this->sharesOrganization($user, $timesheet) && $user->can(Permission::TimesheetViewAny->value);
    }

    public function create(User $user): bool {
        return $user->can(Permission::TimesheetCreate->value);
    }

    public function update(User $user, Timesheet $timesheet): bool {
        return ! $timesheet->isSigned()
            && $this->owns($user, $timesheet, 'user_id')
            && $user->can(Permission::TimesheetUpdate->value);
    }

    public function delete(User $user, Timesheet $timesheet): bool {
        return ! $timesheet->isSigned()
            && $this->owns($user, $timesheet, 'user_id')
            && $user->can(Permission::TimesheetDelete->value);
    }

    public function submit(User $user, Timesheet $timesheet): bool {
        return $this->update($user, $timesheet);
    }

    public function sign(User $user, Timesheet $timesheet): bool {
        return ! $timesheet->isLocked()
            && $this->owns($user, $timesheet, 'user_id')
            && $user->can(Permission::TimesheetSign->value);
    }

    public function lock(User $user, Timesheet $timesheet): bool {
        return $this->sharesOrganization($user, $timesheet) && $user->can(Permission::TimesheetLock->value);
    }

    public function unlock(User $user, Timesheet $timesheet): bool {
        return $this->sharesOrganization($user, $timesheet) && $user->can(Permission::TimesheetUnlock->value);
    }
}
