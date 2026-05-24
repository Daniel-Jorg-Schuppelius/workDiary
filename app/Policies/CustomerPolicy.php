<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\UserRole;
use App\Models\{Customer, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class CustomerPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Customer $customer): bool {
        return true;
    }

    public function create(User $user): bool {
        return $user->canManageBilling() || $user->hasRole(UserRole::User->value);
    }

    public function update(User $user, Customer $customer): bool {
        return $user->canManageBilling() || $this->owns($user, $customer, 'created_by');
    }

    /**
     * Hardes Löschen nur wenn keine Projekte und keine externen Referenzen
     * (z. B. Lexoffice-Kontakte) am Kunden hängen. Sonst bitte archivieren.
     */
    public function delete(User $user, Customer $customer): bool {
        if (! $user->canManageBilling()) {
            return false;
        }

        if ($customer->hasProjects()) {
            return false;
        }

        return ! $customer->externalReferences()->exists();
    }

    public function archive(User $user, Customer $customer): bool {
        return $user->canManageBilling() || $this->owns($user, $customer, 'created_by');
    }

    public function restore(User $user, Customer $customer): bool {
        return $this->archive($user, $customer);
    }

    public function pushToLexoffice(User $user, Customer $customer): bool {
        return $user->canManageBilling();
    }
}
