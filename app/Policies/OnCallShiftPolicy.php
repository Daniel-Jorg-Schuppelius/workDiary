<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallShiftPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\OnCallShift;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class OnCallShiftPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function view(User $user, OnCallShift $shift): bool {
        return $this->owns($user, $shift);
    }

    public function update(User $user, OnCallShift $shift): bool {
        return $this->owns($user, $shift);
    }

    public function delete(User $user, OnCallShift $shift): bool {
        return $this->owns($user, $shift);
    }
}
