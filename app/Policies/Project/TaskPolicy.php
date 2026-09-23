<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Task, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

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
        return true;
    }

    public function update(User $user, Task $task): bool {
        return $this->owns($user, $task, 'created_by') || $this->participatesInProject($user, $task);
    }

    public function delete(User $user, Task $task): bool {
        return $this->owns($user, $task, 'created_by') || $this->participatesInProject($user, $task);
    }

    /**
     * Erlaubt das Planen (Bearbeiten/Löschen) durch Personen, die dem Auftrag
     * zugeordnet sind – Mitglieder eines zugewiesenen Teams oder Einzelmitglieder.
     * So können „einzelne Mitarbeiter ihre Projekte planen".
     */
    private function participatesInProject(User $user, Task $task): bool {
        $project = $task->project;

        return $project !== null && $project->assignableUsers()->contains('id', (int) $user->id);
    }
}
