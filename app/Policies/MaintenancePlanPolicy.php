<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlanPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{MaintenancePlan, User};
use App\Policies\Concerns\HasAdminBypass;

class MaintenancePlanPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::AssetView->value);
    }

    public function view(User $user, MaintenancePlan $plan): bool {
        return $user->can(P::AssetView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::AssetUpdate->value);
    }

    public function update(User $user, MaintenancePlan $plan): bool {
        return $user->can(P::AssetUpdate->value);
    }

    public function delete(User $user, MaintenancePlan $plan): bool {
        return $user->can(P::AssetUpdate->value);
    }

    public function complete(User $user, MaintenancePlan $plan): bool {
        return $user->can(P::AssetUpdate->value);
    }
}
