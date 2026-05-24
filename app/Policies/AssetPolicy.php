<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Asset, User};
use App\Policies\Concerns\HasAdminBypass;

class AssetPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::AssetView->value);
    }

    public function view(User $user, Asset $asset): bool {
        return $user->can(P::AssetView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::AssetCreate->value);
    }

    public function update(User $user, Asset $asset): bool {
        return $user->can(P::AssetUpdate->value);
    }

    public function decommission(User $user, Asset $asset): bool {
        return $user->can(P::AssetDecommission->value);
    }

    public function transferOwnership(User $user, Asset $asset): bool {
        return $user->can(P::AssetTransferOwnership->value);
    }
}
