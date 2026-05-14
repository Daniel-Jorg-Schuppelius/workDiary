<?php

namespace App\Policies;

use App\Models\OnCallShift;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class OnCallShiftPolicy
{
    use ChecksOwnership;
    use HasAdminBypass;

    public function view(User $user, OnCallShift $shift): bool
    {
        return $this->owns($user, $shift);
    }

    public function update(User $user, OnCallShift $shift): bool
    {
        return $this->owns($user, $shift);
    }

    public function delete(User $user, OnCallShift $shift): bool
    {
        return $this->owns($user, $shift);
    }
}
