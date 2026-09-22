<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupChangeProposalPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubGroupChangeProposal;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Wechsel-/Prüfvorschläge (MVP-842): Liste für Register-Leser und
 * Gruppenleitung; entscheiden darf die Verwaltung oder die Leitung der
 * betroffenen Gruppe.
 */
class ClubGroupChangeProposalPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubGroupChangeProposal $proposal): bool {
        $group = $proposal->group;

        return $this->canReadRegister($user) || ($group !== null && $this->leadsGroup($user, $group));
    }

    public function decide(User $user, ClubGroupChangeProposal $proposal): bool {
        $group = $proposal->group;

        return $this->canManage($user) || ($group !== null && $this->leadsGroup($user, $group));
    }
}
