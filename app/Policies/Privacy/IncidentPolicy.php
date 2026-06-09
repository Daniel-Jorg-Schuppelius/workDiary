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

/** Datenschutzvorfaelle. Ohne Admin-Bypass, organisationsgebunden. */
class IncidentPolicy {
    private function sameOrg(User $user, Incident $incident): bool {
        return (int) $user->organization_id === (int) $incident->organization_id;
    }

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.incident.manage');
    }

    public function view(User $user, Incident $incident): bool {
        return $this->sameOrg($user, $incident) && $user->can('dataprotection.incident.manage');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.incident.manage');
    }

    public function update(User $user, Incident $incident): bool {
        return $this->sameOrg($user, $incident) && $user->can('dataprotection.incident.manage');
    }
}
