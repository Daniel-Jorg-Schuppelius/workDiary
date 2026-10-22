<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Diary;

use App\Enums\User\Permission;
use App\Models\Diary\Tour;
use App\Models\Platform\User;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class TourPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(Permission::TourViewAny->value);
    }

    public function view(User $user, Tour $tour): bool {
        return $this->owns($user, $tour, 'user_id') || $this->managesForOthers($user, $tour);
    }

    /** Eigene Touren legt jeder an; für andere Fahrer braucht es tour.manage (TourController). */
    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Tour $tour): bool {
        return $this->owns($user, $tour, 'user_id') || $this->managesForOthers($user, $tour);
    }

    public function delete(User $user, Tour $tour): bool {
        return $this->update($user, $tour);
    }

    private function managesForOthers(User $user, Tour $tour): bool {
        return $this->sharesOrganization($user, $tour) && $user->can(Permission::TourManage->value);
    }
}
