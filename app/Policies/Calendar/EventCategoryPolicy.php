<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventCategoryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\UserRole;
use App\Models\{EventCategory, User};
use App\Policies\Concerns\HasAdminBypass;

class EventCategoryPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, EventCategory $category): bool {
        return true;
    }

    public function create(User $user): bool {
        return $user->hasRole(UserRole::TrainingManager->value);
    }

    public function update(User $user, EventCategory $category): bool {
        return $user->hasRole(UserRole::TrainingManager->value);
    }

    public function delete(User $user, EventCategory $category): bool {
        return $user->hasRole(UserRole::TrainingManager->value);
    }
}
