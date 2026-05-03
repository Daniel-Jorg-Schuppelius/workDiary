<?php

namespace App\Policies;

use App\Models\EmergencyAssignment;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

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
