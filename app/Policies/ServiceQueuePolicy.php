<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceQueuePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{ServiceQueue, User};
use App\Policies\Concerns\ChecksOwnership;

/**
 * Queue-Verwaltung (Feature 065, MVP-150): Pflege nur mit
 * helpdesk.queue.manage; Sicht folgt dem Ticket-Sichtrecht. Org-Scope
 * kommt aus den Global Scopes — die Policy prüft ihn trotzdem hart
 * (Defense in Depth, Whitebox-Leitplanke).
 */
class ServiceQueuePolicy {
    use ChecksOwnership;

    public function viewAny(User $user): bool {
        return $user->can(Permission::ServiceTicketView->value);
    }

    public function view(User $user, ServiceQueue $queue): bool {
        return $this->sharesOrganization($user, $queue) && $user->can(Permission::ServiceTicketView->value);
    }

    public function create(User $user): bool {
        return $user->can(Permission::HelpdeskQueueManage->value);
    }

    public function update(User $user, ServiceQueue $queue): bool {
        return $this->sharesOrganization($user, $queue) && $user->can(Permission::HelpdeskQueueManage->value);
    }

    public function delete(User $user, ServiceQueue $queue): bool {
        return $this->sharesOrganization($user, $queue) && $user->can(Permission::HelpdeskQueueManage->value);
    }

}
