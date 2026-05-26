<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTemplatePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{InvoiceTemplate, User};

class InvoiceTemplatePolicy {
    public function viewAny(User $user): bool {
        return $user->isAdmin() || $user->hasEffectivePermission(Permission::InvoiceViewAny->value);
    }

    public function view(User $user, InvoiceTemplate $template): bool {
        return $template->organization_id === $user->organization_id
            && $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->isAdmin() || $user->hasEffectivePermission(Permission::InvoiceCreate->value);
    }

    public function update(User $user, InvoiceTemplate $template): bool {
        return $template->organization_id === $user->organization_id
            && ($user->isAdmin() || $user->hasEffectivePermission(Permission::InvoiceUpdate->value));
    }

    public function delete(User $user, InvoiceTemplate $template): bool {
        return $template->organization_id === $user->organization_id
            && ($user->isAdmin() || $user->hasEffectivePermission(Permission::InvoiceDelete->value));
    }
}
