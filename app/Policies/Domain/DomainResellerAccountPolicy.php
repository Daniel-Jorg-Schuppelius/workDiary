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
use App\Policies\PermissionPolicy;

/**
 * Subuser/Subreseller-Sicht (Feature 083): Portfolio/Salden sehen, Kunden
 * zuordnen und Accounting einsehen. Ein Kundenmapping darf NIE einen
 * Providerbenutzer anlegen/umbenennen/verschieben/löschen.
 */
class DomainResellerAccountPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::DomainViewAny,
        'view' => P::DomainView,
        'assignCustomer' => P::DomainCustomerAssign,
        'viewAccounting' => P::DomainAccountingView,
    ];

    public function assignCustomer(User $user, DomainResellerAccount $account): bool {
        return $this->allows($user, 'assignCustomer');
    }

    public function viewAccounting(User $user, DomainResellerAccount $account): bool {
        return $this->allows($user, 'viewAccounting');
    }
}
