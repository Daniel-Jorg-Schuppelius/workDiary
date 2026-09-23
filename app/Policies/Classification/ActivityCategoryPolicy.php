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

use App\Policies\Concerns\HasAdminBypass;

class ActivityCategoryPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => true,
        'view' => true,
        'create' => false, // Pflege nur Admin (before-Hook)
        'update' => false,
        'delete' => false,
    ];
}
