<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAdvisoryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln ISMS-Advisories (Feature 044, MVP 2): Lesen mit
 * isms.view/viewAny, Import (create) nur mit isms.manage. Advisories sind
 * Nachweis-Ablagen ohne Bearbeitung/Löschung über die UI
 * (isms.* wiederverwendet, keine neuen Permissions).
 */
class IsmsAdvisoryPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
    ];
}
