<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionRulePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Sales;

use App\Enums\User\Permission as P;
use App\Models\Sales\CommissionRule;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Provisionsregeln (Feature 146): lesen mit commission.viewAny ODER
 * commission.manage, pflegen nur mit commission.manage.
 */
class CommissionRulePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CommissionViewAny->value) || $user->can(P::CommissionManage->value);
    }

    public function view(User $user, CommissionRule $rule): bool {
        unset($rule);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::CommissionManage->value);
    }

    public function update(User $user, CommissionRule $rule): bool {
        unset($rule);

        return $user->can(P::CommissionManage->value);
    }

    public function delete(User $user, CommissionRule $rule): bool {
        return $this->update($user, $rule);
    }
}
