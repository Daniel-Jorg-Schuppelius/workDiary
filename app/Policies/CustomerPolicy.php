<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

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
        return $user->canManageBilling() || $user->hasRole(User::ROLE_USER);
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
