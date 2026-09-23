<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{Team, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Zugriffsregeln für operative Arbeits-Teams. Verwaltung (Anlegen/Bearbeiten/
 * Löschen/Mitglieder) erfordert die jeweiligen `team.*`-Permissions
 * (per Default: Teamleitung + Admin); Ansehen genügt `team.viewAny`/`team.view`.
 * Admin überspringt via {@see HasAdminBypass}.
 */
class TeamPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->hasEffectivePermission(Permission::TeamViewAny->value);
    }

    public function view(User $user, Team $team): bool {
        return $this->sharesOrganization($user, $team)
            && $user->hasEffectivePermission(Permission::TeamView->value);
    }

    public function create(User $user): bool {
        return $user->organization_id !== null
            && $user->hasEffectivePermission(Permission::TeamCreate->value);
    }

    public function update(User $user, Team $team): bool {
        return $this->sharesOrganization($user, $team)
            && $user->hasEffectivePermission(Permission::TeamUpdate->value);
    }

    public function delete(User $user, Team $team): bool {
        return $this->sharesOrganization($user, $team)
            && $user->hasEffectivePermission(Permission::TeamDelete->value);
    }

    public function manageMembers(User $user, Team $team): bool {
        return $this->sharesOrganization($user, $team)
            && $user->hasEffectivePermission(Permission::TeamManageMembers->value);
    }
}
