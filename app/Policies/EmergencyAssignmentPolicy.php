<?php

namespace App\Policies;

use App\Models\EmergencyAssignment;
use App\Models\User;

class EmergencyAssignmentPolicy {
    public function before(User $user, string $ability): ?bool {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, EmergencyAssignment $assignment): bool {
        return $user->id === $assignment->user_id;
    }

    public function update(User $user, EmergencyAssignment $assignment): bool {
        return $user->id === $assignment->user_id;
    }

    public function delete(User $user, EmergencyAssignment $assignment): bool {
        return $user->id === $assignment->user_id;
    }
}
