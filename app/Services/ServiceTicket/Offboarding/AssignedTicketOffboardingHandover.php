<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignedTicketOffboardingHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket\Offboarding;

use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Org\Contracts\OffboardingHandover;
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Offene, der Person zugewiesene Service-Tickets an eine Nachfolge (Feature
 * 202, MVP-1648; auch beim Austritt) — über die normale Zuweisung, also mit
 * Audit `service_ticket.assigned` und Benachrichtigung der Nachfolge.
 */
final class AssignedTicketOffboardingHandover implements OffboardingHandover {
    public function __construct(private readonly ServiceTicketService $tickets) {}

    public function key(): string {
        return 'assigned_service_tickets';
    }

    public function label(int $count): string {
        return trans_choice('delegation.handover.area.assigned_service_tickets', $count, ['count' => $count]);
    }

    public function count(User $member): int {
        return $this->assigned($member)->count();
    }

    public function handOver(User $member, User $successor, User $actor): int {
        $moved = 0;
        foreach ($this->assigned($member)->orderBy('id')->get() as $ticket) {
            $this->tickets->assign($ticket, $actor, (int) $successor->id);
            $moved++;
        }

        return $moved;
    }

    /** @return Builder<ServiceTicket> */
    private function assigned(User $member): Builder {
        return ServiceTicket::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $member->organization_id)
            ->where('assigned_to_user_id', $member->id)
            ->open();
    }
}
