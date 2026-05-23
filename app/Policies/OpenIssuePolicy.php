<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssuePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\OpenIssue;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class OpenIssuePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        unset($user);

        return true;
    }

    public function view(User $user, OpenIssue $issue): bool {
        return $this->isParticipant($user, $issue)
            || $user->can(P::OpenIssueViewAny->value);
    }

    public function create(User $user): bool {
        unset($user);

        return true;
    }

    public function update(User $user, OpenIssue $issue): bool {
        return $this->isParticipant($user, $issue)
            || $user->can(P::OpenIssueAssign->value);
    }

    public function assign(User $user): bool {
        return $user->can(P::OpenIssueAssign->value);
    }

    public function publishToCustomer(User $user): bool {
        return $user->can(P::OpenIssuePublishToCustomer->value);
    }

    public function delete(User $user, OpenIssue $issue): bool {
        return $this->isParticipant($user, $issue)
            || $user->can(P::OpenIssueDelete->value);
    }

    private function isParticipant(User $user, OpenIssue $issue): bool {
        return (int) $issue->assignee_user_id === (int) $user->id
            || (int) $issue->created_by_user_id === (int) $user->id;
    }
}
