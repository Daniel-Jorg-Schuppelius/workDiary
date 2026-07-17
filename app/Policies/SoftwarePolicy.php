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
use App\Models\Software;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Software gehört zur IT-Asset-Domäne und teilt die Asset-Berechtigungen.
 */
class SoftwarePolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::AssetView,
        'view' => P::AssetView,
        'create' => P::AssetCreate,
        'update' => P::AssetUpdate,
        'delete' => P::AssetUpdate,
    ];
}
