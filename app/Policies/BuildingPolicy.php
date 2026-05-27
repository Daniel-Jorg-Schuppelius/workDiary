<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildingPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Building, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class BuildingPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Building $building): bool {
        return true;
    }

    public function create(User $user): bool {
        return $user->canManageBilling();
    }

    public function update(User $user, Building $building): bool {
        return $user->canManageBilling() || $this->owns($user, $building, 'created_by');
    }

    /**
     * Hardes Löschen nur, wenn keine Geschosse am Gebäude hängen.
     */
    public function delete(User $user, Building $building): bool {
        if (! $user->canManageBilling()) {
            return false;
        }

        return ! $building->floors()->exists();
    }
}
