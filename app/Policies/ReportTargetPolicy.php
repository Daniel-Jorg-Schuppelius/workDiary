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
use App\Models\{ReportTarget, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zielwert-Pflege ist Geschäftsführungs-/Admin-Sache (report.target.manage).
 * Org-Scoping erfolgt über den globalen OrganizationScope des Modells.
 */
class ReportTargetPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(Permission::ReportTargetManage->value);
    }

    public function view(User $user, ReportTarget $target): bool {
        return $user->can(Permission::ReportTargetManage->value);
    }

    public function create(User $user): bool {
        return $user->can(Permission::ReportTargetManage->value);
    }

    public function update(User $user, ReportTarget $target): bool {
        return $user->can(Permission::ReportTargetManage->value);
    }

    public function delete(User $user, ReportTarget $target): bool {
        return $user->can(Permission::ReportTargetManage->value);
    }
}
