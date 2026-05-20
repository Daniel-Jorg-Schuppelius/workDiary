<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkSchedulePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSchedule;
use App\Policies\Concerns\HasAdminBypass;

class WorkSchedulePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, WorkSchedule $schedule): bool {
        return (int) $user->id === (int) $schedule->user_id;
    }

    public function create(User $user): bool {
        return false;
    }

    public function update(User $user, WorkSchedule $schedule): bool {
        return false;
    }

    public function delete(User $user, WorkSchedule $schedule): bool {
        return false;
    }
}
