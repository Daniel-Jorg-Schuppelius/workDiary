<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessorPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\Processor;
use App\Models\User;

/** Dienstleisterstammdaten. Ohne Admin-Bypass, organisationsgebunden. */
class ProcessorPolicy {
    private function sameOrg(User $user, Processor $processor): bool {
        return (int) $user->organization_id === (int) $processor->organization_id;
    }

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.view');
    }

    public function view(User $user, Processor $processor): bool {
        return $this->sameOrg($user, $processor) && $user->can('dataprotection.view');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.avv.manage');
    }

    public function update(User $user, Processor $processor): bool {
        return $this->sameOrg($user, $processor) && $user->can('dataprotection.avv.manage');
    }

    public function delete(User $user, Processor $processor): bool {
        return $this->update($user, $processor);
    }
}
