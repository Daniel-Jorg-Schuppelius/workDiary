<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncidentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\Incident;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

/** Datenschutzvorfaelle. Ohne Admin-Bypass, organisationsgebunden. */
class IncidentPolicy {
    use ChecksOwnership;

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.incident.manage');
    }

    public function view(User $user, Incident $incident): bool {
        return $this->sharesOrganization($user, $incident) && $user->can('dataprotection.incident.manage');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.incident.manage');
    }

    public function update(User $user, Incident $incident): bool {
        return $this->sharesOrganization($user, $incident) && $user->can('dataprotection.incident.manage');
    }
}
