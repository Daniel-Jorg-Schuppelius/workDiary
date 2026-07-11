<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Applications;

use App\Enums\User\Permission as P;
use App\Models\Applications\JobApplication;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Bewerbungsakten (Feature 068, MVP-192): minimaler Zugriffskreis —
 * Unterlagen, Gespräche und Bewertungen sieht nur, wer recruiting.*-Rechte
 * trägt; Datenschutz-Aktionen (Löschung/Auskunft/Talentpool) brauchen
 * zusätzlich recruiting.privacy.
 */
class JobApplicationPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::RecruitingViewAny->value);
    }

    public function view(User $user, JobApplication $application): bool {
        return $user->can(P::RecruitingView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::RecruitingManage->value);
    }

    public function update(User $user, JobApplication $application): bool {
        return $user->can(P::RecruitingManage->value) && ! $application->isAnonymized();
    }

    public function decide(User $user, JobApplication $application): bool {
        return $user->can(P::RecruitingDecide->value) && ! $application->isAnonymized();
    }

    /** Aufbewahrung, Löschung/Anonymisierung, Auskunft, Talentpool. */
    public function privacy(User $user, JobApplication $application): bool {
        return $user->can(P::RecruitingPrivacy->value);
    }
}
