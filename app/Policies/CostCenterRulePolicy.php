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
use App\Models\{CostCenterRule, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Kostenstellen-Regeln (Rang 35): Admin via Bypass; Buchhaltung (Lohnbüro)
 * pflegt Regeln über costCenterRule.viewAny/manage — gleiche Zielgruppe wie
 * die Zuschlagsregeln.
 */
class CostCenterRulePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CostCenterRuleViewAny->value);
    }

    public function create(User $user): bool {
        return $user->can(P::CostCenterRuleManage->value);
    }

    public function update(User $user, CostCenterRule $rule): bool {
        unset($rule);

        return $user->can(P::CostCenterRuleManage->value);
    }

    public function delete(User $user, CostCenterRule $rule): bool {
        unset($rule);

        return $user->can(P::CostCenterRuleManage->value);
    }
}
