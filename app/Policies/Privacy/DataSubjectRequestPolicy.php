<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\DataSubjectRequest;
use App\Models\User;

/**
 * Zugriff auf Betroffenenanfragen. BEWUSST OHNE Admin-Bypass: die Rechte
 * (`dataprotection.*`) liegen ausserhalb des zentralen Permission-Enums, ein
 * Plattform-Admin erhaelt sie also nicht automatisch. Jeder Zugriff ist
 * organisationsgebunden.
 */
class DataSubjectRequestPolicy {
    private function sameOrg(User $user, DataSubjectRequest $request): bool {
        return (int) $user->organization_id === (int) $request->organization_id;
    }

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.dsr.manage');
    }

    public function view(User $user, DataSubjectRequest $request): bool {
        return $this->sameOrg($user, $request) && $user->can('dataprotection.dsr.manage');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.dsr.manage');
    }

    public function update(User $user, DataSubjectRequest $request): bool {
        return $this->sameOrg($user, $request) && $user->can('dataprotection.dsr.manage');
    }

    public function assign(User $user, DataSubjectRequest $request): bool {
        return $this->sameOrg($user, $request) && $user->can('dataprotection.dsr.assign');
    }

    public function export(User $user, DataSubjectRequest $request): bool {
        return $this->sameOrg($user, $request) && $user->can('dataprotection.export');
    }
}
