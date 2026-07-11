<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationContractNegotiationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Applications;

use App\Enums\User\Permission as P;
use App\Models\Applications\{ApplicationContractNegotiation, JobApplication};
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Vertragsverhandlungen (Feature 068, MVP-195): Rechte folgen dem
 * KONTEXT — Personal-Verhandlungen (Konditionen!) verlangen recruiting.*,
 * Auftrags-Verhandlungen tender.*. So bleiben Personal-Konditionen
 * stärker geschützt als normale Projekt-/Auftragsdaten.
 */
class ApplicationContractNegotiationPolicy {
    use HasAdminBypass;

    public function view(User $user, ApplicationContractNegotiation $negotiation): bool {
        return $this->isRecruitingContext($negotiation)
            ? $user->can(P::RecruitingView->value)
            : $user->can(P::TenderView->value);
    }

    public function update(User $user, ApplicationContractNegotiation $negotiation): bool {
        return $this->isRecruitingContext($negotiation)
            ? $user->can(P::RecruitingManage->value)
            : $user->can(P::TenderManage->value);
    }

    /** Freigeben/Abschließen/Ablehnen. */
    public function decide(User $user, ApplicationContractNegotiation $negotiation): bool {
        return $this->isRecruitingContext($negotiation)
            ? $user->can(P::RecruitingDecide->value)
            : $user->can(P::TenderDecide->value);
    }

    private function isRecruitingContext(ApplicationContractNegotiation $negotiation): bool {
        return $negotiation->negotiable_type === (new JobApplication)->getMorphClass();
    }
}
