<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDeviationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{ProcedureDeviation, ProcedureStepRun, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Steuerung der Prozedur-Abweichungen (MVP-029).
 */
class ProcedureDeviationPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ProcedureDeviationView->value);
    }

    public function view(User $user, ProcedureDeviation $deviation): bool {
        return $user->can(P::ProcedureDeviationView->value);
    }

    public function record(User $user, ProcedureStepRun $stepRun): bool {
        return $user->can(P::ProcedureDeviationRecord->value);
    }

    public function update(User $user, ProcedureDeviation $deviation): bool {
        return $user->can(P::ProcedureDeviationUpdate->value)
            || $deviation->created_by_user_id === $user->id;
    }

    public function acceptRisk(User $user, ProcedureDeviation $deviation): bool {
        return $user->can(P::ProcedureDeviationAcceptRisk->value);
    }
}
