<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplatePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{ProcedureTemplate, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class ProcedureTemplatePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ProcedureTemplateView->value);
    }

    public function view(User $user, ProcedureTemplate $template): bool {
        return $this->sharesOrganization($user, $template)
            && $user->can(P::ProcedureTemplateView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::ProcedureTemplateCreate->value);
    }

    public function update(User $user, ProcedureTemplate $template): bool {
        return $this->sharesOrganization($user, $template)
            && $user->can(P::ProcedureTemplateUpdate->value);
    }

    public function publish(User $user, ProcedureTemplate $template): bool {
        return $this->sharesOrganization($user, $template)
            && $user->can(P::ProcedureTemplatePublish->value);
    }
}
