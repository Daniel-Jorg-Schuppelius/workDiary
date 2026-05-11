<?php

namespace App\Policies;

use App\Models\ShiftType;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class ShiftTypePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return false;
    }

    public function view(User $user, ShiftType $shiftType): bool {
        return false;
    }

    public function create(User $user): bool {
        return false;
    }

    public function update(User $user, ShiftType $shiftType): bool {
        return false;
    }

    public function delete(User $user, ShiftType $shiftType): bool {
        return false;
    }
}
