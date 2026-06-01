<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignCustomerPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\UserRole;
use App\Models\{ForeignCustomer, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Berechtigungen für Fremdkunden (Endkunden). Analog {@see CustomerPolicy};
 * zusätzlich `promote` (Fremdkunde → echter Kunde), nur für Buchhaltung/GF.
 */
class ForeignCustomerPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, ForeignCustomer $foreignCustomer): bool {
        return $this->sharesOrganization($user, $foreignCustomer);
    }

    public function create(User $user): bool {
        return $user->canManageBilling() || $user->hasRole(UserRole::User->value);
    }

    public function update(User $user, ForeignCustomer $foreignCustomer): bool {
        return $user->canManageBilling() || $this->owns($user, $foreignCustomer, 'created_by');
    }

    /** Hartes Löschen nur ohne zugeordnete Projekte und externe Referenzen — sonst archivieren. */
    public function delete(User $user, ForeignCustomer $foreignCustomer): bool {
        if (! $user->canManageBilling()) {
            return false;
        }
        if ($foreignCustomer->projects()->exists()) {
            return false;
        }

        return ! $foreignCustomer->externalReferences()->exists();
    }

    public function archive(User $user, ForeignCustomer $foreignCustomer): bool {
        return $user->canManageBilling() || $this->owns($user, $foreignCustomer, 'created_by');
    }

    public function restore(User $user, ForeignCustomer $foreignCustomer): bool {
        return $this->archive($user, $foreignCustomer);
    }

    public function promote(User $user, ForeignCustomer $foreignCustomer): bool {
        return $user->canManageBilling();
    }
}
