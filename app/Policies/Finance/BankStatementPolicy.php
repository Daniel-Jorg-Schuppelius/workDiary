<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankStatementPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\User\Permission as P;
use App\Models\Finance\BankStatement;
use App\Models\User;

/**
 * Policy für Bankauszüge (Feature 045): Import über finance.payment.import,
 * Lesen über finance.viewAny bzw. die Reconcile-Berechtigung.
 */
class BankStatementPolicy {
    public function viewAny(User $user): bool {
        return $user->can(P::FinanceViewAny->value)
            || $user->can(P::FinancePaymentReconcile->value)
            || $user->can(P::FinancePaymentImport->value);
    }

    public function view(User $user, BankStatement $statement): bool {
        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::FinancePaymentImport->value);
    }

    /** Originaldatei herunterladen (pfadsicher im Controller geprüft). */
    public function download(User $user, BankStatement $statement): bool {
        return $this->viewAny($user);
    }
}
