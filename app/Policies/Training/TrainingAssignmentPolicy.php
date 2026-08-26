<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingAssignmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Training;

use App\Enums\User\Permission as P;
use App\Models\Training\TrainingAssignment;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Soll-Einträge (Feature 145): das Register sehen Berechtigte; die eigene
 * Zeile darf jede:r sehen (sie zeigt die eigene Schulungspflicht). Pflege
 * bleibt bei training.manage.
 */
class TrainingAssignmentPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::TrainingViewAny->value) || $user->can(P::TrainingManage->value);
    }

    public function view(User $user, TrainingAssignment $assignment): bool {
        return $this->viewAny($user) || (int) $assignment->user_id === (int) $user->id;
    }

    public function create(User $user): bool {
        return $user->can(P::TrainingManage->value);
    }

    public function update(User $user, TrainingAssignment $assignment): bool {
        unset($assignment);

        return $user->can(P::TrainingManage->value);
    }

    public function delete(User $user, TrainingAssignment $assignment): bool {
        return $this->update($user, $assignment);
    }
}
