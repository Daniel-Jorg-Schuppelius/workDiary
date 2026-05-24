<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexEligibilityPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{FlexEligibility, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Verwaltung der Gleitzeit-Berechtigungs-Perioden eines Mitarbeiters.
 * Bewusst nicht an `manage-members` gekoppelt: die Berechtigung wird
 * über die feingranulare Permission `user.flex.manage` gesteuert, damit
 * z. B. die Buchhaltung Gleitzeit-Zeiträume pflegen kann, ohne den
 * vollen Mitglieder-CRUD zu erhalten.
 */
class FlexEligibilityPolicy {
    use HasAdminBypass;

    public function viewAny(User $user, User $member): bool {
        return $this->sameOrgWithPermission($user, $member);
    }

    public function create(User $user, User $member): bool {
        return $this->sameOrgWithPermission($user, $member);
    }

    public function delete(User $user, FlexEligibility $eligibility): bool {
        $member = $eligibility->user;
        if (! $member instanceof User) {
            return false;
        }

        return $this->sameOrgWithPermission($user, $member);
    }

    private function sameOrgWithPermission(User $actor, User $member): bool {
        if ($actor->organization_id === null || $actor->organization_id !== $member->organization_id) {
            return false;
        }

        return $actor->hasEffectivePermission('user.flex.manage');
    }
}
