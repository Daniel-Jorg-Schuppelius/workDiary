<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSoftwareInstallationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsSoftwareInstallation;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln Software-Installationen (Feature 044): identisch zum
 * Produkt — Lesen mit isms.viewAny/isms.view, Pflege nur mit isms.manage.
 */
class IsmsSoftwareInstallationPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::IsmsViewAny->value);
    }

    public function view(User $user, IsmsSoftwareInstallation $installation): bool {
        return $user->can(P::IsmsView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function update(User $user, IsmsSoftwareInstallation $installation): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function delete(User $user, IsmsSoftwareInstallation $installation): bool {
        return $user->can(P::IsmsManage->value);
    }
}
