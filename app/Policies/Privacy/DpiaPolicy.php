<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DpiaPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\Dpia;
use App\Models\User;

/** Datenschutz-Folgenabschaetzung. Ohne Admin-Bypass, organisationsgebunden. */
class DpiaPolicy {
    private function sameOrg(User $user, Dpia $dpia): bool {
        return (int) $user->organization_id === (int) $dpia->organization_id;
    }

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.view');
    }

    public function view(User $user, Dpia $dpia): bool {
        return $this->sameOrg($user, $dpia) && $user->can('dataprotection.view');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.dpia.manage');
    }

    public function update(User $user, Dpia $dpia): bool {
        return $this->sameOrg($user, $dpia) && $user->can('dataprotection.dpia.manage');
    }
}
