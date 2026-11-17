<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDelegationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Delegation;

use App\Enums\User\Permission;
use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Vertretungen (Feature 202, MVP-1647). Jede Person regelt ihre eigene
 * Vertretung; für andere — etwa aus der Krankmeldung — braucht es
 * `user.delegation.manage` (Leitung). Beenden dürfen die vertretene Person,
 * die Vertretung und die Leitung. Den Anlass (Urlaub, Krankheit) sieht nur die
 * vertretene Person und die Leitung, nie die Vertretung.
 */
class WorkDelegationPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    /** Alle Vertretungen der Organisation sehen und für andere anlegen. */
    public function manage(User $user): bool {
        return $user->can(Permission::UserDelegationManage->value);
    }

    public function create(User $user): bool {
        return true;
    }

    /** Vertretung für $represented anlegen: für sich selbst immer, für andere nur die Leitung. */
    public function createFor(User $user, User $represented): bool {
        if ((int) $represented->organization_id !== (int) $user->organization_id) {
            return false;
        }

        return (int) $represented->id === (int) $user->id || $this->manage($user);
    }

    public function end(User $user, WorkDelegation $delegation): bool {
        if ($delegation->isEnded() || (int) $delegation->organization_id !== (int) $user->organization_id) {
            return false;
        }

        return in_array((int) $user->id, [(int) $delegation->represented_user_id, (int) $delegation->deputy_user_id], true)
            || $this->manage($user);
    }

    /**
     * Endgültige Übergabe der Arbeit eines aktiven Mitglieds ohne Austritt
     * (MVP-1648): Leitung der Vertretungen, nur in der eigenen Organisation,
     * nie die eigene Arbeit und — ohne Org-Admin — nicht die eines Admins.
     * Admins lässt {@see HasAdminBypass} vorab durch; Organisation, eigene
     * Person und Plattform-Admins prüft deshalb auch der Controller.
     */
    public function handOver(User $user, User $member): bool {
        return (int) $member->organization_id === (int) $user->organization_id
            && (int) $member->id !== (int) $user->id
            && ! $member->isDeactivated()
            && (! $member->isGlobalAdmin() || $user->isGlobalAdmin())
            && ($user->isAdmin() || ($this->manage($user) && ! $member->isAdmin()));
    }

    /** Anlass und Abwesenheit sehen: vertretene Person und Leitung. */
    public function viewReason(User $user, WorkDelegation $delegation): bool {
        return (int) $delegation->organization_id === (int) $user->organization_id
            && ((int) $delegation->represented_user_id === (int) $user->id || $this->manage($user));
    }
}
