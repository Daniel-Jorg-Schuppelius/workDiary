<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasAdminBypass.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Concerns;

use App\Models\User;

trait HasAdminBypass {
    public function before(User $user, string $ability): ?bool {
        return $user->isAdmin() ? true : null;
    }
}
