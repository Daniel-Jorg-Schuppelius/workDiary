<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupProofPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{ProcedureBackupProof, ProcedureStepRun, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Steuerung der Backup-Nachweise (MVP-027).
 */
class ProcedureBackupProofPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ProcedureRunView->value);
    }

    public function view(User $user, ProcedureBackupProof $proof): bool {
        return $user->can(P::ProcedureRunView->value);
    }

    public function register(User $user, ProcedureStepRun $stepRun): bool {
        return $user->can(P::ProcedureBackupRegister->value);
    }

    public function verify(User $user, ProcedureBackupProof $proof): bool {
        return $user->can(P::ProcedureBackupVerify->value);
    }

    public function viewExternal(User $user, ProcedureBackupProof $proof): bool {
        return $user->can(P::ProcedureBackupViewExternal->value);
    }
}
