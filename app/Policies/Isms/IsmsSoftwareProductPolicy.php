<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSoftwareProductPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln Softwareinventar (Feature 044): wiederverwendet die
 * bestehenden ISMS-Permissions — Lesen mit isms.viewAny/isms.view,
 * Pflege nur mit isms.manage (Standard: nur admin). KEINE eigenen
 * Software-Permissions.
 */
class IsmsSoftwareProductPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
        'update' => P::IsmsManage,
        'delete' => P::IsmsManage,
    ];
}
