<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MilestonePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Milestone, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class MilestonePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Milestone $milestone): bool {
        return true;
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Milestone $milestone): bool {
        return $this->owns($user, $milestone, 'created_by');
    }

    public function delete(User $user, Milestone $milestone): bool {
        return $this->owns($user, $milestone, 'created_by');
    }
}
