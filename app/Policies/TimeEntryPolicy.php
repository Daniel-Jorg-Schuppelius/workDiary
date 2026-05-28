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

use App\Models\{TimeEntry, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};
use App\Services\Timekeeping\TimeEntryEditPolicy;

class TimeEntryPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, TimeEntry $entry): bool {
        if ($this->owns($user, $entry, 'user_id')) {
            return true;
        }
        if (! $this->sharesOrganization($user, $entry)) {
            return false;
        }

        return $user->canManageBilling();
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, TimeEntry $entry): bool {
        if (! $this->owns($user, $entry, 'user_id')) {
            return false;
        }

        return app(TimeEntryEditPolicy::class)->canSelfEdit($entry);
    }

    public function delete(User $user, TimeEntry $entry): bool {
        if (! $this->owns($user, $entry, 'user_id')) {
            return false;
        }

        return app(TimeEntryEditPolicy::class)->canSelfEdit($entry);
    }
}
