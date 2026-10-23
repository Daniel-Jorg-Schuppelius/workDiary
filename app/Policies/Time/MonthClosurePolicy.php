<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosurePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Time;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\User\Permission as P;
use App\Models\Platform\User;
use App\Models\Time\MonthClosure;
use App\Policies\Concerns\ChecksOwnership;
use App\Services\Approval\SelfApproval;
use App\Services\Users\TeamScope;

/**
 * Berechtigungen für Monatsfreigaben (MVP-016, ../WorkDiary-Architecture/konzepte/monatsfreigabe.md §6).
 */
class MonthClosurePolicy {
    use ChecksOwnership;

    /** Admin-Durchgriff — außer bei Freigabe und Ablehnung, dort gilt die Selbstfreigabe-Regel auch für Admins. */
    public function before(User $user, string $ability): ?bool {
        return ! in_array($ability, ['approve', 'reject'], true) && $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool {
        return $user->can(P::MonthViewOwn->value)
            || $user->can(P::MonthViewTeam->value)
            || $user->can(P::MonthViewOrganization->value);
    }

    public function view(User $user, MonthClosure $closure): bool {
        if (! $this->sharesOrganization($user, $closure)) {
            return false;
        }
        if ($this->owns($user, $closure)) {
            return $user->can(P::MonthViewOwn->value);
        }

        return ($user->can(P::MonthViewOrganization->value) || $user->can(P::MonthViewTeam->value))
            && $this->inScope($user, $closure);
    }

    public function submit(User $user, MonthClosure $closure): bool {
        return $this->sharesOrganization($user, $closure)
            && $this->owns($user, $closure)
            && $user->can(P::MonthSubmitOwn->value)
            && in_array($closure->status, [
                MonthClosureStatus::Draft,
                MonthClosureStatus::Reopened,
                MonthClosureStatus::Rejected,
            ], true);
    }

    public function approve(User $user, MonthClosure $closure): bool {
        return $closure->status === MonthClosureStatus::Submitted && $this->decides($user, $closure, P::MonthApprove);
    }

    public function reject(User $user, MonthClosure $closure): bool {
        return $closure->status === MonthClosureStatus::Submitted && $this->decides($user, $closure, P::MonthReject);
    }

    public function reopen(User $user, MonthClosure $closure): bool {
        if (! $this->sharesOrganization($user, $closure)) {
            return false;
        }

        // Self-Reopen aus 'rejected' durch den Betroffenen
        if (
            $closure->status === MonthClosureStatus::Rejected
            && $this->owns($user, $closure)
            && $user->can(P::MonthSubmitOwn->value)
        ) {
            return true;
        }

        return $user->can(P::MonthReopen->value)
            && $this->inScope($user, $closure)
            && in_array($closure->status, [
                MonthClosureStatus::Approved,
                MonthClosureStatus::Locked,
                MonthClosureStatus::Rejected,
            ], true);
    }

    public function lock(User $user, MonthClosure $closure): bool {
        return $this->sharesOrganization($user, $closure)
            && $user->can(P::MonthLock->value)
            && $this->inScope($user, $closure)
            && $closure->status === MonthClosureStatus::Approved;
    }

    /** Prüfpaket (Rang 40): nur freigegebene/gesperrte Monate, Sperr-Recht. */
    public function bundle(User $user, MonthClosure $closure): bool {
        return $this->sharesOrganization($user, $closure)
            && $user->can(P::MonthLock->value)
            && in_array($closure->status, [MonthClosureStatus::Approved, MonthClosureStatus::Locked], true);
    }

    /**
     * Freigabe oder Ablehnung: nie der eigene Monat (S-35), außer niemand
     * sonst darf entscheiden; mit Team-Recht nur Monate des eigenen Teams.
     */
    private function decides(User $user, MonthClosure $closure, P $permission): bool {
        if ($this->owns($user, $closure) && ! app(SelfApproval::class)->soleDecider($user, $permission)) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        return $this->sharesOrganization($user, $closure)
            && $user->can($permission->value)
            && $this->inScope($user, $closure);
    }

    private function inScope(User $user, MonthClosure $closure): bool {
        return app(TeamScope::class)->covers($user, (int) $closure->user_id, P::MonthViewTeam, P::MonthViewOrganization);
    }
}
