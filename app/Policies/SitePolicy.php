<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SitePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Site, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class SitePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Site $site): bool {
        return true;
    }

    public function create(User $user): bool {
        return $user->canManageBilling();
    }

    public function update(User $user, Site $site): bool {
        return $user->canManageBilling() || $this->owns($user, $site, 'created_by');
    }

    /**
     * Hardes Löschen nur, wenn keine Gebäude am Standort hängen.
     */
    public function delete(User $user, Site $site): bool {
        if (! $user->canManageBilling()) {
            return false;
        }

        return ! $site->buildings()->exists();
    }
}
