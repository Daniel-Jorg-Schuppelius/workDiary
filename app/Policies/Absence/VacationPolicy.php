<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Absence;

use App\Enums\User\Permission;
use App\Enums\Vacation\VacationStatus;
use App\Models\Absence\Vacation;
use App\Models\Platform\User;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class VacationPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Vacation $vacation): bool {
        return $this->owns($user, $vacation);
    }

    public function create(User $user): bool {
        return $user->can(Permission::VacationRequest->value);
    }

    public function update(User $user, Vacation $vacation): bool {
        // Nur Antragssteller darf ändern, solange noch ausstehend
        return $this->owns($user, $vacation)
            && $vacation->status === VacationStatus::Pending
            && $user->can(Permission::VacationRequest->value);
    }

    public function delete(User $user, Vacation $vacation): bool {
        return $this->update($user, $vacation);
    }

    /** Genehmigen / Ablehnen: vacation.approve oder die Stellvertretung eines abwesenden Admins (MVP-523). */
    public function decide(User $user, Vacation $vacation): bool {
        return $this->sharesOrganization($user, $vacation)
            // Keine Selbstfreigabe (S-35): auch eine Stellvertretung
            // entscheidet nicht über den eigenen Urlaub.
            && ! $this->owns($user, $vacation)
            && (
                $user->can(Permission::VacationApprove->value)
                || app(\App\Services\Users\DeputyResolver::class)->actsAsDeputyForAbsentAdmin($user)
            );
    }

    /** Stornieren, sofern noch nicht abgeschlossen: der Antragsteller oder org-weit mit vacation.cancel. */
    public function cancel(User $user, Vacation $vacation): bool {
        if (! in_array($vacation->status, [VacationStatus::Pending, VacationStatus::Approved], true)) {
            return false;
        }

        return ($this->owns($user, $vacation) && $user->can(Permission::VacationRequest->value))
            || ($this->sharesOrganization($user, $vacation) && $user->can(Permission::VacationCancel->value));
    }
}
