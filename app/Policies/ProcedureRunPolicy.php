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
use App\Models\{ProcedureRun, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class ProcedureRunPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ProcedureRunView->value);
    }

    public function view(User $user, ProcedureRun $run): bool {
        return $this->sharesOrganization($user, $run)
            && $user->can(P::ProcedureRunView->value);
    }

    public function start(User $user): bool {
        return $user->can(P::ProcedureRunStart->value);
    }

    public function execute(User $user, ProcedureRun $run): bool {
        return $this->sharesOrganization($user, $run)
            && $user->can(P::ProcedureRunExecute->value);
    }

    public function abort(User $user, ProcedureRun $run): bool {
        if (! $this->sharesOrganization($user, $run)) {
            return false;
        }
        if ($user->can(P::ProcedureRunAbort->value)) {
            return true;
        }

        return $this->owns($user, $run, 'created_by_user_id') && $user->can(P::ProcedureRunStart->value);
    }
}
