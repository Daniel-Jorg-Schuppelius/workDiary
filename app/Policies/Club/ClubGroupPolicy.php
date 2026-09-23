<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubGroup;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Gruppen (MVP-842): Register mit club.viewAny/manage; Gruppenleitung sieht
 * und bedient nur ihre eigenen Gruppen (Aufnahme, Anträge, Beenden).
 * Ausnahmen von den Kriterien und Stammdaten nur mit club.manage.
 */
class ClubGroupPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubGroup $group): bool {
        return $this->canReadRegister($user) || $this->leadsGroup($user, $group);
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, ClubGroup $group): bool {
        unset($group);

        return $this->canManage($user);
    }

    public function delete(User $user, ClubGroup $group): bool {
        return $this->update($user, $group);
    }

    /** Aufnahme, Antrag freigeben/ablehnen, Zuordnung beenden, Vorschläge entscheiden. */
    public function decide(User $user, ClubGroup $group): bool {
        return $this->canManage($user) || $this->leadsGroup($user, $group);
    }

    /** Ausnahme von Alters-/Kriterienregeln — nur Vereinsverwaltung. */
    public function override(User $user, ClubGroup $group): bool {
        unset($group);

        return $this->canManage($user);
    }
}
