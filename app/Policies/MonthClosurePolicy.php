<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosurePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\User\Permission as P;
use App\Models\{MonthClosure, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Berechtigungen für Monatsfreigaben (MVP-016, docs/monatsfreigabe.md §6).
 */
class MonthClosurePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::MonthViewOwn->value)
            || $user->can(P::MonthViewTeam->value)
            || $user->can(P::MonthViewOrganization->value);
    }

    public function view(User $user, MonthClosure $closure): bool {
        if ($user->organization_id !== $closure->organization_id) {
            return false;
        }
        if ($user->id === $closure->user_id) {
            return $user->can(P::MonthViewOwn->value);
        }

        return $user->can(P::MonthViewOrganization->value)
            || $user->can(P::MonthViewTeam->value);
    }

    public function submit(User $user, MonthClosure $closure): bool {
        return $user->organization_id === $closure->organization_id
            && $user->id === $closure->user_id
            && $user->can(P::MonthSubmitOwn->value)
            && in_array($closure->status, [
                MonthClosureStatus::Draft,
                MonthClosureStatus::Reopened,
                MonthClosureStatus::Rejected,
            ], true);
    }

    public function approve(User $user, MonthClosure $closure): bool {
        return $user->organization_id === $closure->organization_id
            && $user->can(P::MonthApprove->value)
            && $closure->status === MonthClosureStatus::Submitted;
    }

    public function reject(User $user, MonthClosure $closure): bool {
        return $user->organization_id === $closure->organization_id
            && $user->can(P::MonthReject->value)
            && $closure->status === MonthClosureStatus::Submitted;
    }

    public function reopen(User $user, MonthClosure $closure): bool {
        if ($user->organization_id !== $closure->organization_id) {
            return false;
        }

        // Self-Reopen aus 'rejected' durch den Betroffenen
        if (
            $closure->status === MonthClosureStatus::Rejected
            && $user->id === $closure->user_id
            && $user->can(P::MonthSubmitOwn->value)
        ) {
            return true;
        }

        return $user->can(P::MonthReopen->value)
            && in_array($closure->status, [
                MonthClosureStatus::Approved,
                MonthClosureStatus::Locked,
                MonthClosureStatus::Rejected,
            ], true);
    }

    public function lock(User $user, MonthClosure $closure): bool {
        return $user->organization_id === $closure->organization_id
            && $user->can(P::MonthLock->value)
            && $closure->status === MonthClosureStatus::Approved;
    }
}
