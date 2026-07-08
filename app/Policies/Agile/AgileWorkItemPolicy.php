<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileWorkItemPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Agile;

use App\Enums\User\Permission;
use App\Models\Agile\AgileWorkItem;
use App\Models\User;

/** Arbeitselement-Policy (Feature 064) — immer inkl. Projektsicht. */
class AgileWorkItemPolicy {
    public function view(User $user, AgileWorkItem $item): bool {
        return $user->can(Permission::AgileView->value)
            && $this->projectVisible($user, $item);
    }

    public function move(User $user, AgileWorkItem $item): bool {
        return $user->can(Permission::AgileWorkItemMove->value)
            && $this->projectVisible($user, $item);
    }

    public function prioritize(User $user, AgileWorkItem $item): bool {
        return $user->can(Permission::AgileBacklogPrioritize->value)
            && $this->projectVisible($user, $item);
    }

    private function projectVisible(User $user, AgileWorkItem $item): bool {
        $project = $item->board?->project;

        return $project !== null && $user->can('view', $project);
    }
}
