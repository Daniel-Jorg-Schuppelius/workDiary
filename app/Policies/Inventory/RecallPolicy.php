<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Enums\User\Permission as P;
use App\Models\Inventory\Recall;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/** Rückrufaktionen (MVP-921): eigene Rechte `recall.*`. */
class RecallPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::RecallViewAny->value);
    }

    public function view(User $user, Recall $recall): bool {
        return $user->can(P::RecallView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::RecallManage->value);
    }

    public function update(User $user, Recall $recall): bool {
        return $user->can(P::RecallManage->value);
    }
}
