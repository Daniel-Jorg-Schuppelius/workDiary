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
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;

/**
 * Zugriffsregeln Normanforderungen + SoA (Feature 044/046):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view (Anforderungen + SoA einsehen).
 * - Pflege inkl. Annex-A-Katalog-Import und Statement-Bearbeitung nur
 *   mit isms.manage. ApplicabilityStatements haben bewusst KEINE eigene
 *   Policy — sie werden über updateStatement() hier autorisiert.
 */
class IsmsRequirementPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::IsmsViewAny,
        'view' => P::IsmsView,
        'create' => P::IsmsManage,
        'update' => P::IsmsManage,
        'delete' => P::IsmsManage,
        'import' => P::IsmsManage,
        'updateStatement' => P::IsmsManage,
    ];

    /** Annex-A-Katalog laden (idempotenter Import, RequirementService). */
    public function import(User $user): bool {
        return $this->allows($user, 'import');
    }

    /** SoA-Aussage (ApplicabilityStatement) eines Scopes bearbeiten. */
    public function updateStatement(User $user): bool {
        return $this->allows($user, 'updateStatement');
    }
}
