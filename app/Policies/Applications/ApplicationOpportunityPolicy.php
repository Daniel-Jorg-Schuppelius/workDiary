<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationOpportunityPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Applications;

use App\Enums\User\Permission as P;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/** Auftragsbewerbungen (Feature 068): eigener Rechtebereich tender.*. */
class ApplicationOpportunityPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::TenderViewAny->value);
    }

    public function view(User $user, ApplicationOpportunity $opportunity): bool {
        return $user->can(P::TenderView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::TenderManage->value);
    }

    public function update(User $user, ApplicationOpportunity $opportunity): bool {
        return $user->can(P::TenderManage->value);
    }

    public function delete(User $user, ApplicationOpportunity $opportunity): bool {
        // Nur nie eingereichte Akten sind löschbar — Einreichungen sind Nachweise.
        return $user->can(P::TenderManage->value)
            && $opportunity->isOpen()
            && ! $opportunity->submissions()->exists();
    }

    /** Go-/No-go, Einreichung, Zuschlag und Überführung. */
    public function decide(User $user, ApplicationOpportunity $opportunity): bool {
        return $user->can(P::TenderDecide->value);
    }
}
