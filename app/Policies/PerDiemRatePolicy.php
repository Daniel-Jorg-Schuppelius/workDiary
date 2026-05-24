<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRatePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{PerDiemRate, User};
use App\Policies\Concerns\HasAdminBypass;

class PerDiemRatePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, PerDiemRate $rate): bool {
        return true;
    }

    public function create(User $user): bool {
        return false;
    }

    public function update(User $user, PerDiemRate $rate): bool {
        return false;
    }

    public function delete(User $user, PerDiemRate $rate): bool {
        return false;
    }
}
