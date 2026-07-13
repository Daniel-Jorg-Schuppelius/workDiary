<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{Problem, User};

/**
 * Problem-Management (Feature 065, MVP-156): Sicht folgt dem Ticket-
 * Sichtrecht, Pflege (Anlage, Bearbeitung, Statuswechsel, Wirksamkeits-
 * prüfung, Known-Error-Veröffentlichung) nur mit service_desk.problem.manage.
 * Org-Scope kommt aus den Global Scopes — die Policy prüft ihn trotzdem
 * hart (Defense in Depth, Muster RequestItemPolicy).
 */
class ProblemPolicy {
    public function viewAny(User $user): bool {
        return $user->can(Permission::ServiceTicketView->value);
    }

    public function view(User $user, Problem $problem): bool {
        return $this->sameOrg($user, $problem) && $user->can(Permission::ServiceTicketView->value);
    }

    public function create(User $user): bool {
        return $user->can(Permission::ServiceDeskProblemManage->value);
    }

    public function update(User $user, Problem $problem): bool {
        return $this->sameOrg($user, $problem) && $user->can(Permission::ServiceDeskProblemManage->value);
    }

    private function sameOrg(User $user, Problem $problem): bool {
        return (int) $user->organization_id === (int) $problem->organization_id;
    }
}
