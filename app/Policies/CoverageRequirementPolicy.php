<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoverageRequirementPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{CoverageRequirement, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class CoverageRequirementPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, CoverageRequirement $requirement): bool {
        return $this->sharesOrganization($user, $requirement);
    }

    public function create(User $user): bool {
        return false;
    }

    public function update(User $user, CoverageRequirement $requirement): bool {
        return false;
    }

    public function delete(User $user, CoverageRequirement $requirement): bool {
        return false;
    }
}
