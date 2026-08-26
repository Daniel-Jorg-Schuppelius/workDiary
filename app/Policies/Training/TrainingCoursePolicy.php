<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingCoursePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Training;

use App\Enums\User\Permission as P;
use App\Models\Training\TrainingCourse;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Schulungskatalog (Feature 145) nach dem Muster des Arbeitsschutz-
 * Registers (132): lesen mit training.viewAny ODER training.manage,
 * pflegen nur mit training.manage.
 */
class TrainingCoursePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::TrainingViewAny->value) || $user->can(P::TrainingManage->value);
    }

    public function view(User $user, TrainingCourse $course): bool {
        unset($course);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::TrainingManage->value);
    }

    public function update(User $user, TrainingCourse $course): bool {
        unset($course);

        return $user->can(P::TrainingManage->value);
    }

    public function delete(User $user, TrainingCourse $course): bool {
        return $this->update($user, $course);
    }
}
