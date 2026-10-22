<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Time;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Time\TimeEntry;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};
use App\Services\Timekeeping\TimeEntryEditPolicy;

class TimeEntryPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(Permission::TimeEntryViewOwn->value) || $user->canViewAllTimeEntries();
    }

    public function view(User $user, TimeEntry $entry): bool {
        if ($this->owns($user, $entry, 'user_id') && $user->can(Permission::TimeEntryViewOwn->value)) {
            return true;
        }
        if (! $this->sharesOrganization($user, $entry)) {
            return false;
        }

        return $user->canViewAllTimeEntries();
    }

    public function create(User $user): bool {
        return $user->can(Permission::TimeEntryCreate->value);
    }

    public function update(User $user, TimeEntry $entry): bool {
        return $this->owns($user, $entry, 'user_id')
            && $user->can(Permission::TimeEntryUpdate->value)
            && app(TimeEntryEditPolicy::class)->canSelfEdit($entry);
    }

    public function delete(User $user, TimeEntry $entry): bool {
        return $this->owns($user, $entry, 'user_id')
            && $user->can(Permission::TimeEntryDelete->value)
            && app(TimeEntryEditPolicy::class)->canSelfEdit($entry);
    }
}
