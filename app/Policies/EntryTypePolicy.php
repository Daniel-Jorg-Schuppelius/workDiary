<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{EntryType, User};
use App\Policies\Concerns\HasAdminBypass;

class EntryTypePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->isAdmin();
    }

    public function view(User $user, EntryType $type): bool {
        return $user->isAdmin();
    }

    public function create(User $user): bool {
        return $user->isAdmin();
    }

    public function update(User $user, EntryType $type): bool {
        return $user->isAdmin();
    }

    public function delete(User $user, EntryType $type): bool {
        return $user->isAdmin();
    }
}
