<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequestItemPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{RequestItem, User};

/**
 * Servicekatalog-Pflege (Feature 065, MVP-154): Sicht folgt dem
 * Ticket-Sichtrecht, Pflege nur mit service_catalog.manage. Org-Scope
 * kommt aus den Global Scopes — die Policy prüft ihn trotzdem hart
 * (Defense in Depth, Muster ServiceQueuePolicy).
 */
class RequestItemPolicy {
    public function viewAny(User $user): bool {
        return $user->can(Permission::ServiceTicketView->value);
    }

    public function view(User $user, RequestItem $item): bool {
        return $this->sameOrg($user, $item) && $user->can(Permission::ServiceTicketView->value);
    }

    public function create(User $user): bool {
        return $user->can(Permission::ServiceCatalogManage->value);
    }

    public function update(User $user, RequestItem $item): bool {
        return $this->sameOrg($user, $item) && $user->can(Permission::ServiceCatalogManage->value);
    }

    public function delete(User $user, RequestItem $item): bool {
        return $this->sameOrg($user, $item) && $user->can(Permission::ServiceCatalogManage->value);
    }

    private function sameOrg(User $user, RequestItem $item): bool {
        return (int) $user->organization_id === (int) $item->organization_id;
    }
}
