<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileBoardPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Agile;

use App\Enums\User\Permission;
use App\Models\Agile\AgileBoard;
use App\Models\{Project, User};

/**
 * Projektboard-Policy (Feature 064): agile Rechte öffnen NIE fremde
 * Projekte — jede Prüfung verlangt zusätzlich ProjectPolicy::view (DoD 064).
 */
class AgileBoardPolicy {
    public function view(User $user, AgileBoard $board): bool {
        return $user->can(Permission::AgileView->value)
            && $this->canViewProject($user, $board->project);
    }

    public function manage(User $user, AgileBoard $board): bool {
        return $user->can(Permission::AgileBoardManage->value)
            && $this->canViewProject($user, $board->project);
    }

    public function activate(User $user, Project $project): bool {
        return $user->can(Permission::AgileBoardManage->value)
            && $user->can('view', $project);
    }

    private function canViewProject(User $user, ?Project $project): bool {
        return $project !== null && $user->can('view', $project);
    }
}
