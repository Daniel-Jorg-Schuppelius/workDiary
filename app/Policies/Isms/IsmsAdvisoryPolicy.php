<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAdvisoryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsAdvisory;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln ISMS-Advisories (Feature 044, MVP 2): Lesen mit
 * isms.view/viewAny, Import (create) nur mit isms.manage. Advisories sind
 * Nachweis-Ablagen ohne Bearbeitung/Löschung über die UI
 * (isms.* wiederverwendet, keine neuen Permissions).
 */
class IsmsAdvisoryPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::IsmsViewAny->value);
    }

    public function view(User $user, IsmsAdvisory $advisory): bool {
        return $user->can(P::IsmsView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }
}
