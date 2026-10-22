<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftTypePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Schedule;

use App\Enums\User\Permission;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

class ShiftTypePolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => Permission::ShiftTypeManage,
        'view' => Permission::ShiftTypeManage,
        'create' => Permission::ShiftTypeManage,
        'update' => Permission::ShiftTypeManage,
        'delete' => Permission::ShiftTypeManage,
    ];
}
