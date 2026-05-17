<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityCategoryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\ActivityCategory;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class ActivityCategoryPolicy
{
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ActivityCategory $c): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false; // only admin via before-hook
    }

    public function update(User $user, ActivityCategory $c): bool
    {
        return false;
    }

    public function delete(User $user, ActivityCategory $c): bool
    {
        return false;
    }
}
