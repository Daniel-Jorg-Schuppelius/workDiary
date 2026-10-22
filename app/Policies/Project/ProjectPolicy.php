<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Project;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class ProjectPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Project $project): bool {
        if (! $user->can(Permission::ProjectView->value)) {
            return false;
        }
        $customer = $project->customer;
        if ($customer === null) {
            return $this->sharesOrganization($user, $project);
        }

        return $this->sharesOrganization($user, $customer);
    }

    public function create(User $user): bool {
        return $user->can(Permission::ProjectCreate->value);
    }

    public function update(User $user, Project $project): bool {
        return $this->owns($user, $project, 'created_by');
    }

    /** Archivieren: wer ändern darf, oder org-weit mit project.archive. */
    public function archive(User $user, Project $project): bool {
        return $this->update($user, $project)
            || ($this->sharesOrganization($user, $project) && $user->can(Permission::ProjectArchive->value));
    }

    public function delete(User $user, Project $project): bool {
        if ($project->is_default) {
            return false;
        }
        if ($project->children()->exists()) {
            return false;
        }

        return $this->sharesOrganization($user, $project) && $user->can(Permission::ProjectDelete->value);
    }
}
