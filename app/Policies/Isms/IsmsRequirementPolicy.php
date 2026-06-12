<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRequirementPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsRequirement;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln Normanforderungen + SoA (Feature 044/046):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (Anforderungen + SoA einsehen).
 * - Pflege inkl. Annex-A-Katalog-Import und Statement-Bearbeitung nur
 *   mit isms.manage. ApplicabilityStatements haben bewusst KEINE eigene
 *   Policy — sie werden über updateStatement() hier autorisiert.
 */
class IsmsRequirementPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::IsmsViewAny->value);
    }

    public function view(User $user, IsmsRequirement $requirement): bool {
        return $user->can(P::IsmsView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function update(User $user, IsmsRequirement $requirement): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function delete(User $user, IsmsRequirement $requirement): bool {
        return $user->can(P::IsmsManage->value);
    }

    /** Annex-A-Katalog laden (idempotenter Import, RequirementService). */
    public function import(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    /** SoA-Aussage (ApplicabilityStatement) eines Scopes bearbeiten. */
    public function updateStatement(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }
}
