<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingRequirementPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Training;

use App\Enums\User\Permission as P;
use App\Models\Training\TrainingRequirement;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Pflichtmatrix (Feature 145): sehen mit training.viewAny/manage, pflegen
 * nur mit training.manage — sie steuert, wer welche Schulung schuldet.
 */
class TrainingRequirementPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::TrainingViewAny->value) || $user->can(P::TrainingManage->value);
    }

    public function view(User $user, TrainingRequirement $requirement): bool {
        unset($requirement);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::TrainingManage->value);
    }

    public function update(User $user, TrainingRequirement $requirement): bool {
        unset($requirement);

        return $user->can(P::TrainingManage->value);
    }

    public function delete(User $user, TrainingRequirement $requirement): bool {
        return $this->update($user, $requirement);
    }
}
