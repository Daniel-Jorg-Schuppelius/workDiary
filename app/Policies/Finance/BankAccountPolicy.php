<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankAccountPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Enums\User\Permission as P;
use App\Models\Finance\BankAccount;
use App\Models\User;

/**
 * Policy für eigene Bankkonten (Feature 045): Verwaltung über finance.config
 * (Admin/Finanzkonfiguration). Lesen für alle, die den Finanzbereich sehen.
 */
class BankAccountPolicy {
    public function viewAny(User $user): bool {
        return $user->can(P::FinanceViewAny->value) || $user->can(P::FinanceConfig->value);
    }

    public function view(User $user, BankAccount $account): bool {
        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::FinanceConfig->value);
    }

    public function update(User $user, BankAccount $account): bool {
        return $user->can(P::FinanceConfig->value);
    }

    public function delete(User $user, BankAccount $account): bool {
        return $user->can(P::FinanceConfig->value);
    }
}
