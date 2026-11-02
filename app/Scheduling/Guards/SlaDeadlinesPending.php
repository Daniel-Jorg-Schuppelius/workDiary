<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaDeadlinesPending.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling\Guards;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\ServiceTicket\ServiceTicket;
use App\Scheduling\Contracts\JobGuard;

/** SLA-Fristen (tickets:scan-sla-breaches): nur, wenn ein offenes Ticket eine verstrichene, noch nicht vermerkte Frist hat — dieselbe Bedingung wie der Befehl. */
final class SlaDeadlinesPending implements JobGuard {
    public function shouldRun(): bool {
        $now = now();

        return ServiceTicket::query()
            ->whereNotIn('status', [ServiceTicketStatus::Closed->value, ServiceTicketStatus::Rejected->value])
            ->where(function ($q) use ($now): void {
                $q->where(function ($q2) use ($now): void {
                    $q2->whereNotNull('reaction_due_at')->where('reaction_breached', false)
                        ->where('reaction_due_at', '<', $now)->whereNull('acknowledged_at');
                })->orWhere(function ($q2) use ($now): void {
                    $q2->whereNotNull('resolution_due_at')->where('resolution_breached', false)
                        ->where('resolution_due_at', '<', $now)->whereNull('resolved_at');
                });
            })
            ->exists();
    }
}
