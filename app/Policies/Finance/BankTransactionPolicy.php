<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankTransactionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\User\Permission as P;
use App\Models\Finance\BankTransaction;
use App\Models\User;

/**
 * Policy für Bankumsätze (Feature 045): Zuordnungen bestätigen/zurücknehmen
 * über finance.payment.reconcile. Bankumsätze selbst sind NIE editierbar —
 * es gibt deshalb bewusst keine update/delete-Methode.
 */
class BankTransactionPolicy {
    public function viewAny(User $user): bool {
        return $user->can(P::FinanceViewAny->value) || $user->can(P::FinancePaymentReconcile->value);
    }

    public function view(User $user, BankTransaction $transaction): bool {
        return $this->viewAny($user);
    }

    /** Zuordnung bestätigen / ignorieren / als nicht zuordenbar markieren / unmatch. */
    public function reconcile(User $user, BankTransaction $transaction): bool {
        return $user->can(P::FinancePaymentReconcile->value);
    }
}
