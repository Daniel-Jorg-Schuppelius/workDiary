<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsControlPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsControl;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln ISMS-Maßnahmenkatalog/SoA (Feature 044):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (SoA einsehen).
 * - Pflege inkl. Annex-A-Katalog-Import nur mit isms.manage.
 */
class IsmsControlPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::IsmsViewAny->value);
    }

    public function view(User $user, IsmsControl $control): bool {
        return $user->can(P::IsmsView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function update(User $user, IsmsControl $control): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function delete(User $user, IsmsControl $control): bool {
        return $user->can(P::IsmsManage->value);
    }

    /** Annex-A-Katalog laden (idempotenter Import, ControlService). */
    public function import(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }
}
