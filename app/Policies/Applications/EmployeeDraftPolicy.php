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
use App\Policies\PermissionPolicy;

/** Mitarbeiter-Entwürfe (Feature 068, MVP-193): HR-Bereich (recruiting.*). */
class EmployeeDraftPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::RecruitingViewAny,
        'view' => P::RecruitingView,
        'update' => P::RecruitingManage,
        'invite' => P::RecruitingDecide,
    ];

    /** Bewusste Übernahme in den Invite-Pfad — Entscheidungsrecht nötig. */
    public function invite(User $user, EmployeeDraft $draft): bool {
        return $this->allows($user, 'invite');
    }
}
