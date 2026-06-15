<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSupplierAssessmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Isms;

use App\Enums\User\Permission as P;
use App\Models\Isms\IsmsSupplierAssessment;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Zugriffsregeln ISMS-Lieferantenbewertung (Feature 044, MVP 2/3):
 * - admin: alles (before()-Bypass).
 * - geschaeftsfuehrung: viewAny/view.
 * - Pflege (inkl. Statusübergänge) nur mit isms.manage (bestehende isms.*
 *   wiederverwendet, KEINE neuen Permissions).
 */
class IsmsSupplierAssessmentPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::IsmsViewAny->value);
    }

    public function view(User $user, IsmsSupplierAssessment $assessment): bool {
        return $user->can(P::IsmsView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function update(User $user, IsmsSupplierAssessment $assessment): bool {
        return $user->can(P::IsmsManage->value);
    }

    public function delete(User $user, IsmsSupplierAssessment $assessment): bool {
        return $user->can(P::IsmsManage->value);
    }

    /** Statusübergang (State-Machine im SupplierAssessmentService). */
    public function transition(User $user, IsmsSupplierAssessment $assessment): bool {
        return $user->can(P::IsmsManage->value);
    }
}
