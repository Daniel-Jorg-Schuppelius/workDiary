<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaBreachScanCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\ServiceTicket;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\ServiceTicket;
use App\Services\ServiceTicket\SlaViolationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SlaBreachScanCommand extends Command {
    protected $signature = 'tickets:scan-sla-breaches';

    protected $description = 'Markiert Reaktions-/Lösungs-SLA-Verletzungen offener Service-Tickets, schreibt das Verletzungsregister und auditiert sie.';

    public function handle(SlaViolationService $violations): int {
        $now = Carbon::now();
        $reactionBreached = 0;
        $resolutionBreached = 0;

        ServiceTicket::query()
            ->whereNotIn('status', [
                ServiceTicketStatus::Closed->value,
                ServiceTicketStatus::Rejected->value,
            ])
            ->where(function ($q) use ($now): void {
                $q->where(function ($q2) use ($now): void {
                    $q2->whereNotNull('reaction_due_at')
                        ->where('reaction_breached', false)
                        ->where('reaction_due_at', '<', $now)
                        ->whereNull('acknowledged_at');
                })->orWhere(function ($q2) use ($now): void {
                    $q2->whereNotNull('resolution_due_at')
                        ->where('resolution_breached', false)
                        ->where('resolution_due_at', '<', $now)
                        ->whereNull('resolved_at');
                });
            })
            ->chunkById(200, function (Collection $tickets) use ($now, $violations, &$reactionBreached, &$resolutionBreached): void {
                /** @var Collection<int, ServiceTicket> $tickets */
                foreach ($tickets as $ticket) {
                    $changed = false;
                    if (
                        ! $ticket->reaction_breached
                        && $ticket->reaction_due_at !== null
                        && $ticket->acknowledged_at === null
                        && $ticket->reaction_due_at->lessThan($now)
                    ) {
                        $ticket->reaction_breached = true;
                        $changed = true;
                        $reactionBreached++;
                        $ticket->audit('service_ticket.sla_reaction_breached', [
                            'due_at' => $ticket->reaction_due_at->toIso8601String(),
                        ]);
                        // Verletzungsregister (Feature 010): je Ticket+Typ genau einmal.
                        $violations->recordResponseBreach($ticket, $now);
                    }
                    if (
                        ! $ticket->resolution_breached
                        && $ticket->resolution_due_at !== null
                        && $ticket->resolved_at === null
                        && $ticket->resolution_due_at->lessThan($now)
                    ) {
                        $ticket->resolution_breached = true;
                        $changed = true;
                        $resolutionBreached++;
                        $ticket->audit('service_ticket.sla_resolution_breached', [
                            'due_at' => $ticket->resolution_due_at->toIso8601String(),
                        ]);
                        $violations->recordResolutionBreach($ticket, $now);
                    }
                    if ($changed) {
                        $ticket->saveQuietly();
                    }
                }
            });

        $this->info(sprintf(
            'SLA-Scan abgeschlossen: %d Reaktionsverletzungen, %d Lösungsverletzungen.',
            $reactionBreached,
            $resolutionBreached
        ));

        return self::SUCCESS;
    }
}
