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

class MaintenancePlanPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::AssetView,
        'view' => P::AssetView,
        'create' => P::AssetUpdate,
        'update' => P::AssetUpdate,
        'delete' => P::AssetUpdate,
        'complete' => P::AssetUpdate,
    ];

    public function complete(User $user, MaintenancePlan $plan): bool {
        return $this->allows($user, 'complete');
    }
}
