<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellerAccountPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Domain;

use App\Enums\User\Permission as P;
use App\Models\Domain\DomainResellerAccount;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Subuser/Subreseller-Sicht (Feature 083): Portfolio/Salden sehen, Kunden
 * zuordnen und Accounting einsehen. Ein Kundenmapping darf NIE einen
 * Providerbenutzer anlegen/umbenennen/verschieben/löschen.
 */
class DomainResellerAccountPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::DomainViewAny->value);
    }

    public function view(User $user, DomainResellerAccount $account): bool {
        return $user->can(P::DomainView->value);
    }

    public function assignCustomer(User $user, DomainResellerAccount $account): bool {
        return $user->can(P::DomainCustomerAssign->value);
    }

    public function viewAccounting(User $user, DomainResellerAccount $account): bool {
        return $user->can(P::DomainAccountingView->value);
    }
}
