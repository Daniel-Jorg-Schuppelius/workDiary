<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{SafetyEvent, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Sicherheitsereignis-Register (Feature 013).
 *
 * - viewAny/view: safety.viewAny ODER safety.manage; der Melder sieht das
 *   eigene Ereignis immer (Außendienst meldet, sieht aber das Register nicht).
 * - create (melden): safety.report ODER safety.manage.
 * - update/transition/delete: safety.manage (Leitung/Admin).
 */
class SafetyEventPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::SafetyViewAny->value) || $user->can(P::SafetyManage->value);
    }

    public function view(User $user, SafetyEvent $event): bool {
        return (int) $event->reported_by_user_id === (int) $user->id
            || $user->can(P::SafetyViewAny->value)
            || $user->can(P::SafetyManage->value);
    }

    public function create(User $user): bool {
        return $user->can(P::SafetyReport->value) || $user->can(P::SafetyManage->value);
    }

    public function update(User $user, SafetyEvent $event): bool {
        unset($event);

        return $user->can(P::SafetyManage->value);
    }

    public function delete(User $user, SafetyEvent $event): bool {
        unset($event);

        return $user->can(P::SafetyManage->value);
    }
}
