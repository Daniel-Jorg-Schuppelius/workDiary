<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Classification, User};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Steuerung der Klassifikationen (MVP-030).
 *
 * Plattform-Defaults (organization_id = NULL) dürfen ausschließlich
 * von Trägern der platform.manage-Permission bearbeitet werden.
 */
class ClassificationPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ClassificationList->value)
            || $user->can(P::ClassificationOrgView->value);
    }

    public function view(User $user, Classification $classification): bool {
        return $user->can(P::ClassificationList->value)
            || $user->can(P::ClassificationOrgView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::ClassificationOrgManage->value)
            || $user->can(P::ClassificationPlatformManage->value);
    }

    public function update(User $user, Classification $classification): bool {
        if ($classification->isPlatformDefault()) {
            return $user->can(P::ClassificationPlatformManage->value);
        }

        return $user->can(P::ClassificationOrgManage->value);
    }

    public function delete(User $user, Classification $classification): bool {
        if ($classification->isPlatformDefault()) {
            return $user->can(P::ClassificationPlatformManage->value);
        }

        return $user->can(P::ClassificationOrgManage->value);
    }

    public function deactivateDefault(User $user): bool {
        return $user->can(P::ClassificationOrgDeactivateDefault->value);
    }

    public function import(User $user): bool {
        return $user->can(P::ClassificationOrgImport->value);
    }
}
