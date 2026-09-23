<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberFormatPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class NumberFormatPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::NumberFormatManage,
        'view' => P::NumberFormatManage,
        'manage' => P::NumberFormatManage,
        'update' => P::NumberFormatManage,
    ];

    public function manage(User $user): bool {
        return $this->allows($user, 'manage');
    }
}
