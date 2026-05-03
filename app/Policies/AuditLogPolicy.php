<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy {
    public function before(User $user, string $ability): ?bool {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool {
        return false;
    }

    public function view(User $user, AuditLog $log): bool {
        return false;
    }
}
