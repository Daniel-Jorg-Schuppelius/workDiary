<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleReservationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{User, VehicleReservation};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Fahrzeug-Reservierungen (Feature 028). Sehen darf jeder, der Fahrzeuge
 * sehen darf; reservieren/freigeben erfordert die Disponier-Permission
 * `vehicle.reserve`. Der Erfasser darf seine eigene Reservierung stets
 * stornieren. Admin-Bypass über {@see HasAdminBypass::before()}.
 */
class VehicleReservationPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(Permission::VehicleViewAny->value)
            || $user->can(Permission::VehicleReserve->value);
    }

    public function view(User $user, VehicleReservation $reservation): bool {
        return $this->sharesOrganization($user, $reservation)
            && ($user->can(Permission::VehicleViewAny->value) || $user->can(Permission::VehicleReserve->value));
    }

    public function create(User $user): bool {
        return $user->can(Permission::VehicleReserve->value);
    }

    public function delete(User $user, VehicleReservation $reservation): bool {
        return $this->sharesOrganization($user, $reservation)
            && (
                $this->owns($user, $reservation, 'reserved_by_user_id')
                || $user->can(Permission::VehicleReserve->value)
            );
    }
}
