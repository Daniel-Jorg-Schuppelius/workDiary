<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Enums\Club\ClubGroupMembershipStatus;
use App\Enums\User\Permission as P;
use App\Models\Club\{ClubGroup, ClubMember};
use App\Models\Platform\User;

/**
 * Gemeinsame Rechteprüfungen der Vereins-Policies (MVP-842): Register lesen
 * (club.viewAny/club.manage), pflegen (club.manage) und Gruppenleitung
 * (club.groups.lead — sieht nur die eigenen Gruppen und deren Mitglieder).
 */
trait ClubAccess {
    protected function canReadRegister(User $user): bool {
        return $user->can(P::ClubViewAny->value) || $user->can(P::ClubManage->value);
    }

    protected function canManage(User $user): bool {
        return $user->can(P::ClubManage->value);
    }

    protected function isGroupLead(User $user): bool {
        return $user->can(P::ClubGroupLead->value);
    }

    protected function leadsGroup(User $user, ClubGroup $group): bool {
        return $this->isGroupLead($user) && $group->isLedBy($user);
    }

    /** Mitglied gehört (aktiv) zu einer Gruppe, die der Nutzer leitet. */
    protected function leadsMemberGroup(User $user, ClubMember $member): bool {
        if (! $this->isGroupLead($user)) {
            return false;
        }

        return $member->groupMemberships()
            ->where('status', ClubGroupMembershipStatus::Active->value)
            ->whereHas('group', fn($query) => $query->where('leader_user_id', $user->id))
            ->exists();
    }
}
