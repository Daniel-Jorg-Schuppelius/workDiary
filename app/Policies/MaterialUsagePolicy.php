<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsagePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{MaterialUsage, Timesheet, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class MaterialUsagePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function view(User $user, MaterialUsage $usage): bool {
        return $this->owns($user, $usage->timesheet, 'user_id');
    }

    public function create(User $user, ?Timesheet $timesheet = null): bool {
        if (! $timesheet) {
            return true;
        }

        return $timesheet->canEdit() && $this->owns($user, $timesheet, 'user_id');
    }

    public function update(User $user, MaterialUsage $usage): bool {
        return $usage->timesheet?->canEdit() && $this->owns($user, $usage->timesheet, 'user_id');
    }

    public function delete(User $user, MaterialUsage $usage): bool {
        return $usage->timesheet?->canEdit() && $this->owns($user, $usage->timesheet, 'user_id');
    }
}
