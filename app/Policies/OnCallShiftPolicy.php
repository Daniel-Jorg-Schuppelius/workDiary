<?php

namespace App\Policies;

use App\Models\OnCallShift;
use App\Models\User;

class OnCallShiftPolicy {
    public function before(User $user, string $ability): ?bool {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, OnCallShift $shift): bool {
        return $user->id === $shift->user_id;
    }

    public function update(User $user, OnCallShift $shift): bool {
        return $user->id === $shift->user_id;
    }

    public function delete(User $user, OnCallShift $shift): bool {
        return $user->id === $shift->user_id;
    }
}
