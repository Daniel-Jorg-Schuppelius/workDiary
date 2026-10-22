<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignCustomerPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Customer;

use App\Enums\User\Permission;
use App\Models\Customer\ForeignCustomer;
use App\Models\Platform\User;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Berechtigungen für Fremdkunden (Endkunden). Analog {@see CustomerPolicy};
 * zusätzlich `promote` (Fremdkunde → echter Kunde). Prüfung über die
 * foreignCustomer.*-Rechte (lk-06).
 */
class ForeignCustomerPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, ForeignCustomer $foreignCustomer): bool {
        return $this->sharesOrganization($user, $foreignCustomer) && $user->can(Permission::ForeignCustomerView->value);
    }

    public function create(User $user): bool {
        return $user->can(Permission::ForeignCustomerCreate->value);
    }

    /** Fremde Datensätze mit foreignCustomer.update, eigene Anlagen immer. */
    public function update(User $user, ForeignCustomer $foreignCustomer): bool {
        return $this->owns($user, $foreignCustomer, 'created_by')
            || ($this->sharesOrganization($user, $foreignCustomer) && $user->can(Permission::ForeignCustomerUpdate->value));
    }

    /** Hartes Löschen nur ohne zugeordnete Projekte und externe Referenzen — sonst archivieren. */
    public function delete(User $user, ForeignCustomer $foreignCustomer): bool {
        if (! $this->sharesOrganization($user, $foreignCustomer) || ! $user->can(Permission::ForeignCustomerDelete->value)) {
            return false;
        }
        if ($foreignCustomer->projects()->exists()) {
            return false;
        }

        return ! $foreignCustomer->externalReferences()->exists();
    }

    public function archive(User $user, ForeignCustomer $foreignCustomer): bool {
        return $this->update($user, $foreignCustomer);
    }

    public function restore(User $user, ForeignCustomer $foreignCustomer): bool {
        return $this->archive($user, $foreignCustomer);
    }

    public function promote(User $user, ForeignCustomer $foreignCustomer): bool {
        return $this->sharesOrganization($user, $foreignCustomer) && $user->can(Permission::ForeignCustomerPromote->value);
    }
}
