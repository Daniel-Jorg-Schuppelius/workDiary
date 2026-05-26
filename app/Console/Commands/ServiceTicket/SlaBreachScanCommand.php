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
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SlaBreachScanCommand extends Command {
    protected $signature = 'tickets:scan-sla-breaches';

    protected $description = 'Markiert Reaktions-/Lösungs-SLA-Verletzungen offener Service-Tickets und auditiert sie.';

    public function handle(): int {
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
            ->chunkById(200, function ($tickets) use ($now, &$reactionBreached, &$resolutionBreached): void {
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
