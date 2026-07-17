<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionRequestPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Enums\User\Permission as P;
use App\Models\{TimeCorrectionRequest, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Berechtigungen für Zeit-Korrekturanträge (MVP-017, ../WorkDiary-Architecture/zeit-korrekturen.md §7).
 */
class TimeCorrectionRequestPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CorrectionCreateOwn->value)
            || $user->can(P::CorrectionViewTeam->value)
            || $user->can(P::CorrectionViewOrganization->value);
    }

    public function view(User $user, TimeCorrectionRequest $request): bool {
        if (! $this->sharesOrganization($user, $request)) {
            return false;
        }
        if ($this->owns($user, $request) || $this->owns($user, $request, 'requested_by_user_id')) {
            return true;
        }

        return $user->can(P::CorrectionViewOrganization->value)
            || $user->can(P::CorrectionViewTeam->value);
    }

    public function create(User $user): bool {
        return $user->can(P::CorrectionCreateOwn->value);
    }

    public function submit(User $user, TimeCorrectionRequest $request): bool {
        return $this->sharesOrganization($user, $request)
            && $this->owns($user, $request, 'requested_by_user_id')
            && $user->can(P::CorrectionSubmitOwn->value)
            && $request->status === TimeCorrectionStatus::Draft;
    }

    public function withdraw(User $user, TimeCorrectionRequest $request): bool {
        return $this->sharesOrganization($user, $request)
            && $this->owns($user, $request, 'requested_by_user_id')
            && $user->can(P::CorrectionWithdrawOwn->value)
            && in_array($request->status, [TimeCorrectionStatus::Draft, TimeCorrectionStatus::Submitted], true);
    }

    public function approve(User $user, TimeCorrectionRequest $request): bool {
        return $this->sharesOrganization($user, $request)
            && $user->can(P::CorrectionApprove->value)
            && $request->status === TimeCorrectionStatus::Submitted;
    }

    public function reject(User $user, TimeCorrectionRequest $request): bool {
        return $this->sharesOrganization($user, $request)
            && $user->can(P::CorrectionReject->value)
            && $request->status === TimeCorrectionStatus::Submitted;
    }

    public function apply(User $user, TimeCorrectionRequest $request): bool {
        return $this->sharesOrganization($user, $request)
            && $user->can(P::CorrectionApplySystem->value)
            && $request->status === TimeCorrectionStatus::Approved;
    }
}
