<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\ProcedureRun;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class ProcedureRunPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ProcedureRunView->value);
    }

    public function view(User $user, ProcedureRun $run): bool {
        return $user->organization_id === $run->organization_id
            && $user->can(P::ProcedureRunView->value);
    }

    public function start(User $user): bool {
        return $user->can(P::ProcedureRunStart->value);
    }

    public function execute(User $user, ProcedureRun $run): bool {
        return $user->organization_id === $run->organization_id
            && $user->can(P::ProcedureRunExecute->value);
    }

    public function abort(User $user, ProcedureRun $run): bool {
        if ($user->organization_id !== $run->organization_id) {
            return false;
        }
        if ($user->can(P::ProcedureRunAbort->value)) {
            return true;
        }

        return $run->created_by_user_id === $user->id && $user->can(P::ProcedureRunStart->value);
    }
}
