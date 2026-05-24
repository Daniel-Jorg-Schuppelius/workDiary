<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyAssignmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{EmergencyAssignment, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class EmergencyAssignmentPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function view(User $user, EmergencyAssignment $assignment): bool {
        return $this->owns($user, $assignment);
    }

    public function update(User $user, EmergencyAssignment $assignment): bool {
        return $this->owns($user, $assignment);
    }

    public function delete(User $user, EmergencyAssignment $assignment): bool {
        return $this->owns($user, $assignment);
    }
}
