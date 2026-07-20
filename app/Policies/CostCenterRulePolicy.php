<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenterRulePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Kostenstellen-Regeln (Rang 35): Admin via Bypass; Buchhaltung (Lohnbüro)
 * pflegt Regeln über costCenterRule.viewAny/manage — gleiche Zielgruppe wie
 * die Zuschlagsregeln.
 */
class CostCenterRulePolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::CostCenterRuleViewAny,
        'create' => P::CostCenterRuleManage,
        'update' => P::CostCenterRuleManage,
        'delete' => P::CostCenterRuleManage,
    ];
}
