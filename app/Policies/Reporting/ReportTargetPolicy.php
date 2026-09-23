<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTargetPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zielwert-Pflege ist Geschäftsführungs-/Admin-Sache (report.target.manage).
 * Org-Scoping erfolgt über den globalen OrganizationScope des Modells.
 */
class ReportTargetPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => Permission::ReportTargetManage,
        'view' => Permission::ReportTargetManage,
        'create' => Permission::ReportTargetManage,
        'update' => Permission::ReportTargetManage,
        'delete' => Permission::ReportTargetManage,
    ];
}
