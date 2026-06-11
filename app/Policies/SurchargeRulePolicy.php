<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeRulePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\Surcharge\SurchargeRule;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zuschlagsregeln (Feature 005): Admin via Bypass; Buchhaltung (Lohnbüro)
 * pflegt Regeln über surchargeRule.viewAny/manage laut Seeder-Matrix.
 */
class SurchargeRulePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::SurchargeRuleViewAny->value);
    }

    public function create(User $user): bool {
        return $user->can(P::SurchargeRuleManage->value);
    }

    public function update(User $user, SurchargeRule $rule): bool {
        unset($rule);

        return $user->can(P::SurchargeRuleManage->value);
    }

    public function delete(User $user, SurchargeRule $rule): bool {
        unset($rule);

        return $user->can(P::SurchargeRuleManage->value);
    }
}
