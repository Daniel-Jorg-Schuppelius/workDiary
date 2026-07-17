<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JointControllerAgreementPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\JointControllerAgreement;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;

/** GVV-Register (Art. 26). Nutzt das Vertragsregister-Recht; ohne Admin-Bypass. */
class JointControllerAgreementPolicy {
    use ChecksOwnership;

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.view');
    }

    public function view(User $user, JointControllerAgreement $gvv): bool {
        return $this->sharesOrganization($user, $gvv) && $user->can('dataprotection.view');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.avv.manage');
    }

    public function update(User $user, JointControllerAgreement $gvv): bool {
        return $this->sharesOrganization($user, $gvv) && $user->can('dataprotection.avv.manage');
    }
}
