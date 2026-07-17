<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaViolationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{SlaViolation, User};
use App\Policies\Concerns\HasAdminBypass;

class SlaViolationPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::SlaViewAny,
        'view' => P::SlaViewAny,
        'acknowledge' => P::SlaManage,
    ];

    /** Verletzung quittieren (Sichtung dokumentieren). */
    public function acknowledge(User $user, SlaViolation $violation): bool {
        return $this->allows($user, 'acknowledge');
    }
}
