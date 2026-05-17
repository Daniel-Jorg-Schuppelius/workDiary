<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\TravelLog;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class TravelLogPolicy
{
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TravelLog $log): bool
    {
        return $this->owns($user, $log, 'user_id');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TravelLog $log): bool
    {
        return $this->owns($user, $log, 'user_id');
    }

    public function delete(User $user, TravelLog $log): bool
    {
        return $this->owns($user, $log, 'user_id');
    }
}
