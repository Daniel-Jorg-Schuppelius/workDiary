<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingAgreementPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\ProcessingAgreement;
use App\Models\User;

/** AVV-/Dienstleisterregister. Ohne Admin-Bypass, organisationsgebunden. */
class ProcessingAgreementPolicy {
    private function sameOrg(User $user, ProcessingAgreement $agreement): bool {
        return (int) $user->organization_id === (int) $agreement->organization_id;
    }

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.view');
    }

    public function view(User $user, ProcessingAgreement $agreement): bool {
        return $this->sameOrg($user, $agreement) && $user->can('dataprotection.view');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.avv.manage');
    }

    public function update(User $user, ProcessingAgreement $agreement): bool {
        return $this->sameOrg($user, $agreement) && $user->can('dataprotection.avv.manage');
    }

    public function delete(User $user, ProcessingAgreement $agreement): bool {
        return $this->update($user, $agreement);
    }
}
