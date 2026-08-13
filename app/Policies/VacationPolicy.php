<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\Vacation\VacationStatus;
use App\Models\{User, Vacation};
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
        return true;
    }

    public function update(User $user, Vacation $vacation): bool {
        // Nur Antragssteller darf ändern, solange noch ausstehend
        return $this->owns($user, $vacation) && $vacation->status === VacationStatus::Pending;
    }

    public function delete(User $user, Vacation $vacation): bool {
        return $this->owns($user, $vacation) && $vacation->status === VacationStatus::Pending;
    }

    /** Genehmigen / Ablehnen darf nur der Admin (via HasAdminBypass). */
    public function decide(User $user, Vacation $vacation): bool {
        // Admins via HasAdminBypass::before(); zusätzlich entscheidet die
        // benannte Stellvertretung, solange der Vertretene abwesend ist (MVP-523).
        return $this->sharesOrganization($user, $vacation)
            && $user->actsAsDeputyForAbsentAdmin();
    }

    /** Stornieren darf der Eigentümer, sofern noch nicht entschieden. */
    public function cancel(User $user, Vacation $vacation): bool {
        return $this->owns($user, $vacation)
            && in_array($vacation->status, [VacationStatus::Pending, VacationStatus::Approved], true);
    }
}
