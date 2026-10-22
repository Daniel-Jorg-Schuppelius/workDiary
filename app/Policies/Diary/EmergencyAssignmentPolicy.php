<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyAssignmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Diary;

use App\Enums\User\Permission;
use App\Models\Diary\EmergencyAssignment;
use App\Models\Platform\User;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class EmergencyAssignmentPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    /**
     * Lesen ist teamweit (Sicherheitsscan 2026-08-23, S-39).
     *
     * Ein Dienstplan lebt davon, dass Kolleginnen und Kollegen ihn sehen — die
     * Weboberfläche zeigt seit je alle Bereitschaften der Organisation samt
     * Namen. `view = owns()` war der Ausreißer, nicht die API: dieselbe Sicht,
     * die im Web offen stand, hätte über die Policy verweigert werden müssen.
     * Vereinheitlicht wird deshalb auf das, was tatsächlich gilt.
     *
     * Ändern beim Eigentümer oder org-weit mit `emergency-assignment.manage` (lk-06).
     */
    public function view(User $user, EmergencyAssignment $assignment): bool {
        return $this->sharesOrganization($user, $assignment);
    }

    public function update(User $user, EmergencyAssignment $assignment): bool {
        return $this->owns($user, $assignment)
            || ($this->sharesOrganization($user, $assignment) && $user->can(Permission::EmergencyAssignmentManage->value));
    }

    public function delete(User $user, EmergencyAssignment $assignment): bool {
        return $this->update($user, $assignment);
    }
}
