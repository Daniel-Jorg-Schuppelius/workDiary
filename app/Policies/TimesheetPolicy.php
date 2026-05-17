<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\Timesheet;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class TimesheetPolicy
{
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Timesheet $timesheet): bool
    {
        return $this->owns($user, $timesheet, 'user_id');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Timesheet $timesheet): bool
    {
        if ($timesheet->isSigned()) {
            return false;
        }

        return $this->owns($user, $timesheet, 'user_id');
    }

    public function delete(User $user, Timesheet $timesheet): bool
    {
        if ($timesheet->isSigned()) {
            return false;
        }

        return $this->owns($user, $timesheet, 'user_id');
    }

    public function submit(User $user, Timesheet $timesheet): bool
    {
        return $this->update($user, $timesheet);
    }

    public function sign(User $user, Timesheet $timesheet): bool
    {
        return ! $timesheet->isLocked() && $this->owns($user, $timesheet, 'user_id');
    }

    public function lock(User $user, Timesheet $timesheet): bool
    {
        // Admin via before(); reguläre Owner dürfen nicht locken
        return false;
    }

    public function unlock(User $user, Timesheet $timesheet): bool
    {
        return false;
    }
}
