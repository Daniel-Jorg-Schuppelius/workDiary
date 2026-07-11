<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobRequisitionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Applications;

use App\Enums\User\Permission as P;
use App\Models\Applications\JobRequisition;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/** Stellen (Feature 068): recruiting.* — getrennt von Projekt-/Auftragsrollen. */
class JobRequisitionPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::RecruitingViewAny->value);
    }

    public function view(User $user, JobRequisition $requisition): bool {
        return $user->can(P::RecruitingView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::RecruitingManage->value);
    }

    public function update(User $user, JobRequisition $requisition): bool {
        return $user->can(P::RecruitingManage->value);
    }

    public function delete(User $user, JobRequisition $requisition): bool {
        return $user->can(P::RecruitingManage->value)
            && ! $requisition->applications()->exists();
    }
}
