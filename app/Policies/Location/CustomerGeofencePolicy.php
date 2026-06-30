<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerGeofencePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Location;

use App\Models\Location\CustomerGeofence;
use App\Models\User;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Geofences sind Stammdaten-Konfiguration: lesen darf jeder im Mandanten,
 * verwalten nur, wer Abrechnung/Stammdaten pflegen darf (wie SitePolicy).
 */
class CustomerGeofencePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, CustomerGeofence $geofence): bool {
        return true;
    }

    public function create(User $user): bool {
        return $user->canManageBilling();
    }

    public function update(User $user, CustomerGeofence $geofence): bool {
        return $user->canManageBilling() || $this->owns($user, $geofence, 'created_by');
    }

    public function delete(User $user, CustomerGeofence $geofence): bool {
        return $user->canManageBilling();
    }
}
