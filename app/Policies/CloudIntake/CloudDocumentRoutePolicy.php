<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudDocumentRoutePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\CloudIntake;

use App\Enums\User\Permission as P;
use App\Models\CloudIntake\CloudDocumentRoute;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Ordnerregeln des Cloud-Dokumenteingangs (Feature 080):
 * `cloudIntake.route.manage` pflegt, Lauf-/Vorschau-Sicht darf lesen.
 */
class CloudDocumentRoutePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CloudIntakeRouteManage->value)
            || $user->can(P::CloudIntakeRunPreview->value);
    }

    public function view(User $user, CloudDocumentRoute $route): bool {
        unset($route);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::CloudIntakeRouteManage->value);
    }

    public function update(User $user, CloudDocumentRoute $route): bool {
        unset($route);

        return $user->can(P::CloudIntakeRouteManage->value);
    }

    public function delete(User $user, CloudDocumentRoute $route): bool {
        unset($route);

        return $user->can(P::CloudIntakeRouteManage->value);
    }
}
