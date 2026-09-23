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

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Vehicle;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Fahrzeuge der Organisation: sehen darf sie jede angemeldete Person, die
 * Einzelansicht bleibt beim Stammfahrer. Anlegen, Ändern und Archivieren
 * verlangt `vehicle.manage`; der Admin-Bypass kommt aus
 * {@see HasAdminBypass::before()}.
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

    /**
     * Stammdatenpflege am Fuhrpark ist ein eigenes Recht
     * ({@see Permission::VehicleManage}) — vorher durfte jede angemeldete
     * Person Fahrzeuge anlegen, ändern und archivieren, auch fremde
     * (Sicherheitsaudit 2026-09-17, authz-vehicle-1). Der Admin-Bypass des
     * Traits gilt weiterhin.
     */
    public function create(User $user): bool {
        return $user->can(Permission::VehicleManage->value);
    }

    public function update(User $user, Vehicle $vehicle): bool {
        return $user->can(Permission::VehicleManage->value);
    }

    public function delete(User $user, Vehicle $vehicle): bool {
        return $this->update($user, $vehicle);
    }
}
