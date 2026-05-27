<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FloorPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Floor, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class FloorPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Floor $floor): bool {
        return true;
    }

    public function create(User $user): bool {
        return $user->canManageBilling();
    }

    public function update(User $user, Floor $floor): bool {
        return $user->canManageBilling() || $this->owns($user, $floor, 'created_by');
    }

    /**
     * Hardes Löschen nur, wenn keine Räume am Geschoss hängen.
     */
    public function delete(User $user, Floor $floor): bool {
        if (! $user->canManageBilling()) {
            return false;
        }

        return ! $floor->rooms()->exists();
    }
}
