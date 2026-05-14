<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class AuditLogPolicy
{
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AuditLog $log): bool
    {
        return false;
    }
}
