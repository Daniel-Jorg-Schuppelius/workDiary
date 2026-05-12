<?php

namespace App\Policies;

use App\Models\Qualification;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class QualificationPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Qualification $qualification): bool {
        return $user->organization_id === $qualification->organization_id;
    }

    public function create(User $user): bool {
        return $user->isAdmin();
    }

    public function update(User $user, Qualification $qualification): bool {
        return $user->isAdmin() && $user->organization_id === $qualification->organization_id;
    }

    public function delete(User $user, Qualification $qualification): bool {
        return $user->isAdmin() && $user->organization_id === $qualification->organization_id;
    }
}
