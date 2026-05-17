<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditLogPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
