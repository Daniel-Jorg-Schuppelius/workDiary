<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReadingPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class MeterReadingPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::MeterReadingView,
        'view' => P::MeterReadingView,
        'create' => P::MeterReadingRecord,
        'record' => P::MeterReadingRecord,
    ];

    public function record(User $user): bool {
        return $this->allows($user, 'record');
    }
}
