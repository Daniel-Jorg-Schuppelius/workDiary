<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRiskPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsRisk;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln ISMS-Risikoregister (Feature 044):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (über die .view-Heuristik der Rollen-Matrix).
 * - Pflege (create/update/delete/transition) nur mit isms.manage —
 *   wird per Default an KEINE Standard-Rolle außer admin vergeben.
 */
class IsmsRiskPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
        'update' => P::IsmsManage,
        'delete' => P::IsmsManage,
        'transition' => P::IsmsManage,
    ];

    /** Statusübergang (State-Machine im RiskService). */
    public function transition(User $user, IsmsRisk $risk): bool {
        return $this->allows($user, 'transition');
    }
}
