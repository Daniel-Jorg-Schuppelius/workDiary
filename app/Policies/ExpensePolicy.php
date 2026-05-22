<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpensePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\Expense\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class ExpensePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Expense $expense): bool {
        return $this->owns($user, $expense);
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Expense $expense): bool {
        // Eigentümer darf ändern, solange noch nicht endgültig entschieden
        // (Approved/Reimbursed/Invoiced/Cancelled).
        return $this->owns($user, $expense)
            && in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Pending, ExpenseStatus::Rejected], true);
    }

    public function delete(User $user, Expense $expense): bool {
        return $this->owns($user, $expense)
            && in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Pending, ExpenseStatus::Rejected], true);
    }

    /** Einreichen darf der Eigentümer (Draft → Pending, Rejected → Pending). */
    public function submit(User $user, Expense $expense): bool {
        return $this->owns($user, $expense)
            && in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Rejected], true);
    }

    /** Genehmigen / Ablehnen darf nur Admin (HasAdminBypass). */
    public function decide(User $user, Expense $expense): bool {
        return false;
    }

    /** Stornieren darf der Eigentümer, solange noch nicht final. */
    public function cancel(User $user, Expense $expense): bool {
        return $this->owns($user, $expense) && ! $expense->status->isFinal();
    }

    /** Erstattung verbuchen / als bezahlt markieren: nur Admin. */
    public function reimburse(User $user, Expense $expense): bool {
        return false;
    }
}
