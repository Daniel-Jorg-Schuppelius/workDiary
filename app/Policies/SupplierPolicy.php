<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\UserRole;
use App\Models\{Supplier, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

class SupplierPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Supplier $supplier): bool {
        return $this->sharesOrganization($user, $supplier);
    }

    public function create(User $user): bool {
        return $user->canManageBilling() || $user->hasRole(UserRole::User->value);
    }

    public function update(User $user, Supplier $supplier): bool {
        return $user->canManageBilling() || $this->owns($user, $supplier, 'created_by');
    }

    /**
     * Hartes Löschen nur wenn keine externen Referenzen (z. B. Lexoffice-
     * Kontakte) am Lieferanten hängen. Sonst bitte archivieren.
     */
    public function delete(User $user, Supplier $supplier): bool {
        if (! $user->canManageBilling()) {
            return false;
        }

        return ! $supplier->externalReferences()->exists();
    }

    public function archive(User $user, Supplier $supplier): bool {
        return $user->canManageBilling() || $this->owns($user, $supplier, 'created_by');
    }

    public function restore(User $user, Supplier $supplier): bool {
        return $this->archive($user, $supplier);
    }

    public function pushToLexoffice(User $user, Supplier $supplier): bool {
        return $user->canManageBilling();
    }
}
