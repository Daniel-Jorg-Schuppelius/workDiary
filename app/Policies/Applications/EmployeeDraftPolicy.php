<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmployeeDraftPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Applications;

use App\Enums\User\Permission as P;
use App\Models\Applications\EmployeeDraft;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/** Mitarbeiter-Entwürfe (Feature 068, MVP-193): HR-Bereich (recruiting.*). */
class EmployeeDraftPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::RecruitingViewAny->value);
    }

    public function view(User $user, EmployeeDraft $draft): bool {
        return $user->can(P::RecruitingView->value);
    }

    public function update(User $user, EmployeeDraft $draft): bool {
        return $user->can(P::RecruitingManage->value);
    }

    /** Bewusste Übernahme in den Invite-Pfad — Entscheidungsrecht nötig. */
    public function invite(User $user, EmployeeDraft $draft): bool {
        return $user->can(P::RecruitingDecide->value);
    }
}
