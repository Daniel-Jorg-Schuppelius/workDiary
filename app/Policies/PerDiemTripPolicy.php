<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\Expense\PerDiemTripStatus;
use App\Models\{PerDiemTrip, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class PerDiemTripPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, PerDiemTrip $trip): bool {
        return $this->owns($user, $trip);
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, PerDiemTrip $trip): bool {
        return $this->owns($user, $trip)
            && $trip->status === PerDiemTripStatus::Draft;
    }

    public function delete(User $user, PerDiemTrip $trip): bool {
        return $this->owns($user, $trip)
            && $trip->status === PerDiemTripStatus::Draft;
    }

    public function convert(User $user, PerDiemTrip $trip): bool {
        return $this->owns($user, $trip)
            && $trip->status === PerDiemTripStatus::Draft;
    }

    public function cancel(User $user, PerDiemTrip $trip): bool {
        return $this->owns($user, $trip)
            && $trip->status !== PerDiemTripStatus::Cancelled;
    }
}
