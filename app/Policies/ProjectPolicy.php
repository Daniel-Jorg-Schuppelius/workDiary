<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class ProjectPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Project $project): bool {
        return true;
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Project $project): bool {
        return $this->owns($user, $project, 'created_by');
    }

    public function delete(User $user, Project $project): bool {
        if ($project->is_default) {
            return false;
        }
        if ($project->children()->exists()) {
            return false;
        }

        return false;
    }
}
