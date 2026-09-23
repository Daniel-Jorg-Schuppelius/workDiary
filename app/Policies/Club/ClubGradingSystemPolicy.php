<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradingSystemPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Enums\User\Permission as P;
use App\Models\Club\ClubGradingSystem;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Graduierungsordnungen (MVP-846): Register und Gruppenleitung lesen,
 * Pflege nur mit club.grading.manage (getrennt von der Vereinsverwaltung).
 */
class ClubGradingSystemPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user) || $this->canGrade($user);
    }

    public function view(User $user, ClubGradingSystem $system): bool {
        unset($system);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $this->canGrade($user);
    }

    public function update(User $user, ClubGradingSystem $system): bool {
        unset($system);

        return $this->canGrade($user);
    }

    public function delete(User $user, ClubGradingSystem $system): bool {
        unset($system);

        return $this->canGrade($user);
    }

    /** Anerkennungen, Widerrufe, Nachweise je Mitglied. */
    public function manageMembers(User $user): bool {
        return $this->canGrade($user);
    }

    private function canGrade(User $user): bool {
        return $user->can(P::ClubGradingManage->value);
    }
}
