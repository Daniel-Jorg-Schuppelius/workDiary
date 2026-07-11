<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityAssessmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Sustainability;

use App\Enums\User\Permission as P;
use App\Models\Sustainability\SustainabilityAssessment;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/** ESG-Daten (Feature 071): eigener Rechtebereich sustainability.*. */
class SustainabilityAssessmentPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::SustainabilityViewAny->value);
    }

    public function view(User $user, SustainabilityAssessment $assessment): bool {
        return $user->can(P::SustainabilityView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::SustainabilityManage->value);
    }

    public function update(User $user, SustainabilityAssessment $assessment): bool {
        return $user->can(P::SustainabilityManage->value) && ! $assessment->isFinal();
    }

    /** Finalisieren/Versionieren + Kataloge (Kriterien/Faktoren/Ziele) pflegen. */
    public function manage(User $user, SustainabilityAssessment $assessment): bool {
        return $user->can(P::SustainabilityManage->value);
    }
}
