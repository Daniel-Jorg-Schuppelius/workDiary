<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Travel;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Travel\TravelLog;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class TravelLogPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    /** Das eigene Fahrtenbuch (travel-log.viewAny); fremde Fahrten sieht niemand außer Admins. */
    public function viewAny(User $user): bool {
        return $user->can(Permission::TravelLogViewAny->value);
    }

    public function view(User $user, TravelLog $log): bool {
        return $this->owns($user, $log, 'user_id');
    }

    public function create(User $user): bool {
        return $user->can(Permission::TravelLogManage->value);
    }

    public function update(User $user, TravelLog $log): bool {
        return $this->owns($user, $log, 'user_id') && $user->can(Permission::TravelLogManage->value);
    }

    public function delete(User $user, TravelLog $log): bool {
        return $this->update($user, $log);
    }
}
