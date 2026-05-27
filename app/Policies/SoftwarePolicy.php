<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwarePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Software, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Software gehört zur IT-Asset-Domäne und teilt die Asset-Berechtigungen.
 */
class SoftwarePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::AssetView->value);
    }

    public function view(User $user, Software $software): bool {
        return $user->can(P::AssetView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::AssetCreate->value);
    }

    public function update(User $user, Software $software): bool {
        return $user->can(P::AssetUpdate->value);
    }

    public function delete(User $user, Software $software): bool {
        return $user->can(P::AssetUpdate->value);
    }
}
