<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubHorsePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubHorse;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/** Pferde (MVP-854): lesen wie das Register (auch Gruppenleitung), Profile pflegt die Vereinsverwaltung. */
class ClubHorsePolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubHorse $horse): bool {
        unset($horse);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, ClubHorse $horse): bool {
        unset($horse);

        return $this->canManage($user);
    }

    public function delete(User $user, ClubHorse $horse): bool {
        return $this->update($user, $horse);
    }
}
