<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Qualification, User};
use App\Policies\Concerns\HasAdminBypass;

class QualificationPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Qualification $qualification): bool {
        return $user->organization_id === $qualification->organization_id;
    }

    public function create(User $user): bool {
        return $user->isAdmin();
    }

    public function update(User $user, Qualification $qualification): bool {
        return $user->isAdmin() && $user->organization_id === $qualification->organization_id;
    }

    public function delete(User $user, Qualification $qualification): bool {
        return $user->isAdmin() && $user->organization_id === $qualification->organization_id;
    }
}
