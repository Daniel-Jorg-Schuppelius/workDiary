<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardAssessmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Safety;

use App\Enums\User\Permission as P;
use App\Models\Safety\HazardAssessment;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Gefährdungsbeurteilungen (Feature 132) — nutzt die bestehenden
 * Arbeitsschutz-Rechte (Feature 013): lesen mit safety.viewAny ODER
 * safety.manage, pflegen (anlegen, ändern, Status, Folgeversion, löschen)
 * nur mit safety.manage.
 */
class HazardAssessmentPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::SafetyViewAny->value) || $user->can(P::SafetyManage->value);
    }

    public function view(User $user, HazardAssessment $assessment): bool {
        unset($assessment);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::SafetyManage->value);
    }

    public function update(User $user, HazardAssessment $assessment): bool {
        unset($assessment);

        return $user->can(P::SafetyManage->value);
    }

    public function transition(User $user, HazardAssessment $assessment): bool {
        return $this->update($user, $assessment);
    }

    public function delete(User $user, HazardAssessment $assessment): bool {
        return $this->update($user, $assessment);
    }
}
