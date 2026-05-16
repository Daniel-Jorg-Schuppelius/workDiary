<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class TimeEntryPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, TimeEntry $entry): bool {
        return true;
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, TimeEntry $entry): bool {
        return $this->owns($user, $entry, 'user_id');
    }

    public function delete(User $user, TimeEntry $entry): bool {
        return $this->owns($user, $entry, 'user_id');
    }
}
