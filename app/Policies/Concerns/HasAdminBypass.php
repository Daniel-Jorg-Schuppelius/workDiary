<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait HasAdminBypass {
    public function before(User $user, string $ability): ?bool {
        return $user->isAdmin() ? true : null;
    }
}
