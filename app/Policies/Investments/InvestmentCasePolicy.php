<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentCasePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Investments;

use App\Enums\User\Permission as P;
use App\Models\Investments\InvestmentCase;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Investitionsakten (Feature 069): eigene Rechte — Beträge, Angebote und
 * Finanzierungsannahmen sieht keine normale Projektrolle automatisch;
 * Freigaben verlangen investment.approve.
 */
class InvestmentCasePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::InvestmentViewAny->value);
    }

    public function view(User $user, InvestmentCase $case): bool {
        return $user->can(P::InvestmentView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::InvestmentManage->value);
    }

    public function update(User $user, InvestmentCase $case): bool {
        return $user->can(P::InvestmentManage->value);
    }

    public function delete(User $user, InvestmentCase $case): bool {
        // Nur nie beantragte Ideen sind löschbar — Anträge sind Nachweise.
        return $user->can(P::InvestmentManage->value)
            && ! $case->budgetRequests()->exists();
    }

    /** Budget-/Abweichungsfreigaben (Schwellenwert-Kette). */
    public function approve(User $user, InvestmentCase $case): bool {
        return $user->can(P::InvestmentApprove->value);
    }
}
