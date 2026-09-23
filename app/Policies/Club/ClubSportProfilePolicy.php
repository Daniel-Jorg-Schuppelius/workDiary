<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSportProfilePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubSportProfile;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/** Sportartenprofile (MVP-852): lesen wie das Register (auch Gruppenleitung), ändern nur Vereinsverwaltung. */
class ClubSportProfilePolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubSportProfile $profile): bool {
        unset($profile);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, ClubSportProfile $profile): bool {
        unset($profile);

        return $this->canManage($user);
    }

    public function delete(User $user, ClubSportProfile $profile): bool {
        return $this->update($user, $profile);
    }
}
