<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubMember;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Mitglieder (MVP-842): Register mit club.viewAny/manage; Gruppenleitung
 * sieht Mitglieder ihrer Gruppen. Mitglied und Vertretung arbeiten in
 * „Mein Verein“, nicht auf der Verwaltungsseite. Pflege nur mit club.manage.
 */
class ClubMemberPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    /** Gesamtes Register (alle Mitglieder) — Gruppenleitung ohne dieses Recht sieht nur ihre Gruppen. */
    public function viewRegister(User $user): bool {
        return $this->canReadRegister($user);
    }

    /**
     * Verwaltungsseite des Mitglieds: Registerrecht oder Leitung einer Gruppe
     * des Mitglieds. Mitglied und Vertretung nutzen „Mein Verein“ — die
     * Verwaltungsseite zeigt interne Vermerke und die Kontaktdaten anderer
     * Vertretungen (Entscheidung zu authz-a-9).
     */
    public function view(User $user, ClubMember $member): bool {
        return $this->canReadRegister($user) || $this->leadsMemberGroup($user, $member);
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, ClubMember $member): bool {
        unset($member);

        return $this->canManage($user);
    }

    public function delete(User $user, ClubMember $member): bool {
        return $this->update($user, $member);
    }

    /** Art-Wechsel, Pause, Austritt und Vertretungen. */
    public function manageMembership(User $user, ClubMember $member): bool {
        return $this->update($user, $member);
    }

    public function import(User $user): bool {
        return $this->canManage($user);
    }
}
