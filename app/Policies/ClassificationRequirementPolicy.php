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
use App\Models\ClassificationRequirement;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class ClassificationRequirementPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ClassificationRequirementView->value);
    }

    public function view(User $user, ClassificationRequirement $requirement): bool {
        return $user->can(P::ClassificationRequirementView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::ClassificationRequirementManage->value);
    }

    public function update(User $user, ClassificationRequirement $requirement): bool {
        return $user->can(P::ClassificationRequirementManage->value);
    }

    public function delete(User $user, ClassificationRequirement $requirement): bool {
        return $user->can(P::ClassificationRequirementManage->value);
    }
}
