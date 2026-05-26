<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission as P;
use App\Models\{ServiceTicket, User};
use App\Policies\Concerns\HasAdminBypass;

class ServiceTicketPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ServiceTicketView->value);
    }

    public function view(User $user, ServiceTicket $ticket): bool {
        return $user->can(P::ServiceTicketView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::ServiceTicketCreate->value);
    }

    public function update(User $user, ServiceTicket $ticket): bool {
        return $user->can(P::ServiceTicketUpdate->value);
    }

    public function transition(User $user, ServiceTicket $ticket): bool {
        return $user->can(P::ServiceTicketUpdate->value);
    }

    public function assign(User $user, ServiceTicket $ticket): bool {
        return $user->can(P::ServiceTicketAssign->value);
    }

    public function close(User $user, ServiceTicket $ticket): bool {
        return $user->can(P::ServiceTicketClose->value);
    }

    public function delete(User $user, ServiceTicket $ticket): bool {
        return $user->can(P::ServiceTicketClose->value);
    }
}
