<?php

/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftTypePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\ShiftType;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class ShiftTypePolicy
{
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ShiftType $shiftType): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ShiftType $shiftType): bool
    {
        return false;
    }

    public function delete(User $user, ShiftType $shiftType): bool
    {
        return false;
    }
}
