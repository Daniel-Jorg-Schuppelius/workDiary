<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSecurityIncidentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsSecurityIncident;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln ISMS-Sicherheitsvorfälle (Feature 044, MVP 2):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (über die .view-Heuristik der Rollen-Matrix).
 * - Pflege (create/update/delete/transition) nur mit isms.manage —
 *   es werden bewusst KEINE neuen Permissions eingeführt (isms.* wiederverwendet).
 */
class IsmsSecurityIncidentPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
        'update' => P::IsmsManage,
        'delete' => P::IsmsManage,
        'transition' => P::IsmsManage,
    ];

    /** Statusübergang (State-Machine im SecurityIncidentService). */
    public function transition(User $user, IsmsSecurityIncident $incident): bool {
        return $this->allows($user, 'transition');
    }
}
