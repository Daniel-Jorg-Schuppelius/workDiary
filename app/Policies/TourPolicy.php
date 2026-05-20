<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\Tour;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class TourPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Tour $tour): bool {
        return $this->owns($user, $tour, 'user_id');
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Tour $tour): bool {
        return $this->owns($user, $tour, 'user_id');
    }

    public function delete(User $user, Tour $tour): bool {
        return $this->owns($user, $tour, 'user_id');
    }
}
