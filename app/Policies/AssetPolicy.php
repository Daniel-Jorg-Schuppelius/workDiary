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

class AssetPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::AssetView,
        'view' => P::AssetView,
        'create' => P::AssetCreate,
        'update' => P::AssetUpdate,
        'decommission' => P::AssetDecommission,
        'transferOwnership' => P::AssetTransferOwnership,
        'checkout' => P::AssetCheckout,
        'manageDefects' => P::AssetDefectManage,
    ];

    public function decommission(User $user, Asset $asset): bool {
        return $this->allows($user, 'decommission');
    }

    public function transferOwnership(User $user, Asset $asset): bool {
        return $this->allows($user, 'transferOwnership');
    }

    public function checkout(User $user, Asset $asset): bool {
        return $this->allows($user, 'checkout');
    }

    public function manageDefects(User $user, Asset $asset): bool {
        return $this->allows($user, 'manageDefects');
    }
}
