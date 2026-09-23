<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceRequirementPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubAttendanceRequirement;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/** Nachweisliste (MVP-855): Anforderungen pflegt die Verwaltung, Bericht und Export sehen Register und Gruppenleitung. */
class ClubAttendanceRequirementPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubAttendanceRequirement $requirement): bool {
        unset($requirement);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, ClubAttendanceRequirement $requirement): bool {
        unset($requirement);

        return $this->canManage($user);
    }

    public function delete(User $user, ClubAttendanceRequirement $requirement): bool {
        return $this->update($user, $requirement);
    }
}
