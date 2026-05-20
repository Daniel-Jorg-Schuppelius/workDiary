<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehiclePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Org-wide vehicles: every authenticated user may list them, but only
 * the default driver (or any admin) can update/archive. Admin bypass is
 * handled by {@see HasAdminBypass::before()}.
 */
class VehiclePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Vehicle $vehicle): bool {
        if ($vehicle->default_user_id === null) {
            return true;
        }

        return (int) $vehicle->default_user_id === (int) $user->id;
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Vehicle $vehicle): bool {
        return $vehicle->default_user_id === null
            || (int) $vehicle->default_user_id === (int) $user->id;
    }

    public function delete(User $user, Vehicle $vehicle): bool {
        return $this->update($user, $vehicle);
    }
}
