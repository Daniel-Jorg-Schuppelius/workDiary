<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\ServiceTicket;

use App\Enums\User\Permission as P;
use App\Models\Platform\User;
use App\Models\ServiceTicket\ServiceTicket;
use App\Policies\Concerns\HasAdminBypass;
use App\Policies\PermissionPolicy;
use App\Services\Delegation\ActsForResolver;

class ServiceTicketPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const array ABILITIES = [
        'viewAny' => P::ServiceTicketView,
        'view' => P::ServiceTicketView,
        'create' => P::ServiceTicketCreate,
        'update' => P::ServiceTicketUpdate,
        'transition' => P::ServiceTicketUpdate,
        'assign' => P::ServiceTicketAssign,
        'close' => P::ServiceTicketClose,
        'delete' => P::ServiceTicketClose,
    ];

    /**
     * Vollaudit 2026-07 (M30): vertrauliche Tickets (confidentiality=restricted)
     * sind nur für Bearbeiter, Watcher und das Queue-Team sichtbar — zusätzlich
     * zur allgemeinen View-Berechtigung (Admins via HasAdminBypass).
     */
    public function view(User $user, mixed $model = null): bool {
        if (! $this->allows($user, 'view')) {
            return false;
        }
        if (! $model instanceof ServiceTicket || $model->confidentiality !== 'restricted') {
            return true;
        }
        $ticket = $model;

        // Bearbeiter — auch in Vertretung (Feature 202, MVP-1648); das Leserecht oben prüft die handelnde Person.
        if ($ticket->assigned_to_user_id !== null && app(ActsForResolver::class)->actsFor($user, (int) $ticket->assigned_to_user_id)) {
            return true;
        }
        if ($ticket->watchers()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($ticket->queue_id === null) {
            return false;
        }
        $queueTeamId = $ticket->queue?->team_id;

        return $queueTeamId !== null && $user->teams()->whereKey($queueTeamId)->exists();
    }

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
