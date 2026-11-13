<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Project;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Project\Task;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};
use App\Services\Delegation\ActsForResolver;

class TaskPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Task $task): bool {
        return true;
    }

    public function create(User $user): bool {
        return $user->can(Permission::TaskManage->value);
    }

    public function update(User $user, Task $task): bool {
        return $user->can(Permission::TaskManage->value)
            && ($this->owns($user, $task, 'created_by') || $this->participatesInProject($user, $task) || $this->deputizesFor($user, $task));
    }

    public function delete(User $user, Task $task): bool {
        return $this->update($user, $task);
    }

    /**
     * Erlaubt das Planen (Bearbeiten/Löschen) durch Personen, die dem Auftrag
     * zugeordnet sind – Mitglieder eines zugewiesenen Teams oder Einzelmitglieder.
     * So können „einzelne Mitarbeiter ihre Projekte planen".
     */
    private function participatesInProject(User $user, Task $task): bool {
        return $this->participantId($task, (int) $user->id);
    }

    private function participantId(Task $task, int $userId): bool {
        $project = $task->project;

        return $project !== null && $project->assignableUsers()->contains('id', $userId);
    }

    /**
     * Vertretung (Feature 202, MVP-1648): Die Aufgabe ist einer Person
     * zugewiesen, die der Nutzer heute vertritt, und diese dürfte sie selbst
     * planen (angelegt oder am Projekt beteiligt). Das Recht `task.manage`
     * braucht die handelnde Person selbst (siehe {@see update()}).
     */
    private function deputizesFor(User $user, Task $task): bool {
        $principals = app(ActsForResolver::class)->principalIds($user);
        if ($principals === []) {
            return false;
        }
        $assigned = array_map('intval', array_filter([$task->assigned_to, ...$task->assignees()->pluck('users.id')->all()]));
        foreach (array_intersect($principals, $assigned) as $principalId) {
            if ((int) $task->created_by === (int) $principalId || $this->participantId($task, (int) $principalId)) {
                return true;
            }
        }

        return false;
    }
}
