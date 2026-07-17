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

class ServiceTicketPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::ServiceTicketView,
        'view' => P::ServiceTicketView,
        'create' => P::ServiceTicketCreate,
        'update' => P::ServiceTicketUpdate,
        'transition' => P::ServiceTicketUpdate,
        'assign' => P::ServiceTicketAssign,
        'close' => P::ServiceTicketClose,
        'delete' => P::ServiceTicketClose,
    ];

    public function transition(User $user, ServiceTicket $ticket): bool {
        return $this->allows($user, 'transition');
    }

    public function assign(User $user, ServiceTicket $ticket): bool {
        return $this->allows($user, 'assign');
    }

    public function close(User $user, ServiceTicket $ticket): bool {
        return $this->allows($user, 'close');
    }
}
