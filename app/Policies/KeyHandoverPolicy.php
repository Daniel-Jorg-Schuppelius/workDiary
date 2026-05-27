<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeyHandoverPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{KeyHandover, User};
use App\Policies\Concerns\HasAdminBypass;

class KeyHandoverPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::KeyHandoverView->value);
    }

    public function view(User $user, KeyHandover $handover): bool {
        return $user->can(P::KeyHandoverView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::KeyHandoverRecord->value);
    }

    public function record(User $user): bool {
        return $user->can(P::KeyHandoverRecord->value);
    }
}
