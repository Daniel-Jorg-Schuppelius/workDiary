<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Classification;

use App\Enums\User\Permission;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

class EntryTypePolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => Permission::EntryTypeManage,
        'view' => Permission::EntryTypeManage,
        'create' => Permission::EntryTypeManage,
        'update' => Permission::EntryTypeManage,
        'delete' => Permission::EntryTypeManage,
    ];
}
