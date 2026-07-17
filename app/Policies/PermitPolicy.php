<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermitPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Genehmigungs-Register. Org-Isolation übernimmt der globale
 * OrganizationScope (Route-Binding findet nur Datensätze der aktiven Org).
 */
class PermitPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::PermitViewAny,
        'view' => P::PermitView,
        'create' => P::PermitCreate,
        'update' => P::PermitUpdate,
        'delete' => P::PermitDelete,
    ];
}
