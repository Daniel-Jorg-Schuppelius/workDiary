<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubDepartmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubDepartment;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Abteilungen/Sparten (MVP-842): Referenzdaten — lesen wie das Register
 * (auch Gruppenleitung), pflegen nur mit club.manage.
 */
class ClubDepartmentPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubDepartment $department): bool {
        unset($department);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, ClubDepartment $department): bool {
        unset($department);

        return $this->canManage($user);
    }

    public function delete(User $user, ClubDepartment $department): bool {
        return $this->update($user, $department);
    }
}
