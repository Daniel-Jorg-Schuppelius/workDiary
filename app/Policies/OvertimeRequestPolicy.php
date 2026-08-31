<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OvertimeRequestPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\TimeApproval\OvertimeRequestStatus;
use App\Enums\User\Permission as P;
use App\Models\{OvertimeRequest, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Überstunden-Anträge (MVP-519) — Muster {@see TimeCorrectionRequestPolicy}:
 * Eigentümer sehen/ziehen eigene Anträge, Teamleitung entscheidet.
 */
class OvertimeRequestPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::OvertimeRequestOwn->value)
            || $user->can(P::OvertimeViewTeam->value);
    }

    public function view(User $user, OvertimeRequest $request): bool {
        if (! $this->sharesOrganization($user, $request)) {
            return false;
        }
        if ($this->owns($user, $request) || $this->owns($user, $request, 'requested_by_user_id')) {
            return true;
        }

        return $user->can(P::OvertimeViewTeam->value);
    }

    public function create(User $user): bool {
        return $user->can(P::OvertimeRequestOwn->value);
    }

    public function withdraw(User $user, OvertimeRequest $request): bool {
        return $this->sharesOrganization($user, $request)
            && ($this->owns($user, $request) || $this->owns($user, $request, 'requested_by_user_id'))
            && $user->can(P::OvertimeWithdrawOwn->value)
            && $request->status === OvertimeRequestStatus::Submitted;
    }

    public function decide(User $user, OvertimeRequest $request): bool {
        return $this->sharesOrganization($user, $request)
            && $user->can(P::OvertimeDecide->value)
            // Keine Selbstfreigabe (S-35).
            && ! $this->owns($user, $request, 'requested_by_user_id')
            && ! $this->owns($user, $request)
            && $request->status === OvertimeRequestStatus::Submitted;
    }
}
