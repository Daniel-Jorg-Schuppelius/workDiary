<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{NumberFormat, User};
use App\Policies\Concerns\HasAdminBypass;

class NumberFormatPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::NumberFormatManage->value);
    }

    public function view(User $user, NumberFormat $format): bool {
        return $user->can(P::NumberFormatManage->value);
    }

    public function manage(User $user): bool {
        return $user->can(P::NumberFormatManage->value);
    }

    public function update(User $user, NumberFormat $format): bool {
        return $user->can(P::NumberFormatManage->value);
    }
}
