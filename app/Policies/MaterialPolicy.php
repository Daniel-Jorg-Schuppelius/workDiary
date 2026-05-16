<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\Material;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class MaterialPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Material $material): bool {
        return true;
    }

    public function create(User $user): bool {
        return false;
    }

    public function update(User $user, Material $material): bool {
        return false;
    }

    public function delete(User $user, Material $material): bool {
        return false;
    }
}
