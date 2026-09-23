<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;

class ClassificationRequirementPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::ClassificationRequirementView,
        'view' => P::ClassificationRequirementView,
        'create' => P::ClassificationRequirementManage,
        'update' => P::ClassificationRequirementManage,
        'delete' => P::ClassificationRequirementManage,
    ];
}
