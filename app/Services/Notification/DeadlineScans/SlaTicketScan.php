<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaTicketScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\ServiceTicket;
use App\Services\Notification\NotificationDispatcher;
use App\Services\ServiceTicket\SlaTimer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * SLA-Eskalation (Feature 010): offene Tickets mit gefährdeter/überschrittener
 * Lösungsfrist (resolution_due_at) melden; verletzte eskalieren zusätzlich.
 * Bleibt explizit (C18): die Statusweiche AtRisk/Breached läuft je Zeile über
 * den SlaTimer und passt nicht ins Zwei-Phasen-Skelett.
 */
class SlaTicketScan extends AbstractDeadlineScan {
    public function __construct(private readonly SlaTimer $timer) {}

    public function key(): string {
        return 'sla_tickets';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $now = Carbon::now();
        $sent = 0;

        ServiceTicket::query()
            ->whereNotIn('status', [
                ServiceTicketStatus::Closed->value,
                ServiceTicketStatus::Rejected->value,
            ])
            ->whereNotNull('resolution_due_at')
            ->whereNull('resolved_at')
            ->chunkById(200, function (Collection $tickets) use ($dispatcher, $now, &$sent): void {
                /** @var Collection<int, ServiceTicket> $tickets */
                foreach ($tickets as $ticket) {
                    $status = $this->timer->resolutionStatus($ticket, $now);
                    if ($status === \App\Enums\ServiceTicket\SlaStatus::Breached) {
                        $payload = $this->slaPayload($ticket, 'sla_breached');
                        $sent += $dispatcher->notify(
                            NotificationEvent::SlaBreached,
                            $ticket,
                            $ticket->assignedTo,
                            $payload,
                            dedup: true,
                        );
                        $sent += $dispatcher->escalateIfDue(NotificationEvent::SlaBreached, $ticket, $payload);
                        // Vollaudit 2026-07 (N19): konfigurierte Eskalationskette
                        // des SLA-Vertrags abarbeiten (escalation_level schreitet fort).
                        $sent += $this->advanceEscalationChain($dispatcher, $ticket, $now);
                    } elseif ($status === \App\Enums\ServiceTicket\SlaStatus::AtRisk) {
                        $sent += $dispatcher->notify(
                            NotificationEvent::SlaAtRisk,
                            $ticket,
                            $ticket->assignedTo,
                            $this->slaPayload($ticket, 'sla_at_risk'),
                            dedup: true,
                        );
                    }
                }
            });

        return $sent;
    }

    /**
     * Eskalationskette (Vollaudit 2026-07, N19): Schritte des SLA-Vertrags
     * (after_minutes/notify) gegen die Überschreitung der Lösungsfrist prüfen;
     * `escalation_level` schreitet fort und dedupliziert dadurch selbst.
     * `notify`: numerische User-ID oder Rollen-Slug (erster aktiver Nutzer
     * der Rolle in der Organisation, deterministisch nach ID).
     */
    private function advanceEscalationChain(NotificationDispatcher $dispatcher, ServiceTicket $ticket, Carbon $now): int {
        $contract = $ticket->slaContract;
        $chain = $contract !== null ? array_values((array) $contract->escalation_chain) : [];
        if ($chain === [] || $ticket->resolution_due_at === null) {
            return 0;
        }

        $overdueMinutes = (int) $ticket->resolution_due_at->diffInMinutes($now, false);
        if ($overdueMinutes <= 0) {
            return 0;
        }

        $sent = 0;
        $level = (int) $ticket->escalation_level;
        foreach ($chain as $index => $step) {
            $stepNo = $index + 1;
            if ($stepNo <= $level) {
                continue;
            }
            if ($overdueMinutes < (int) $step['after_minutes']) {
                break;
            }

            $recipient = $this->resolveEscalationRecipient($ticket, (string) $step['notify']);
            $payload = $this->slaPayload($ticket, 'sla_breached');
            $payload['message'] = (string) __('Eskalationsstufe :level erreicht (:minutes Min. über Lösungsfrist).', [
                'level' => $stepNo,
                'minutes' => $overdueMinutes,
            ]);
            $sent += $dispatcher->notify(NotificationEvent::SlaBreached, $ticket, $recipient, $payload);

            $ticket->forceFill(['escalation_level' => $stepNo])->save();
            $level = $stepNo;
        }

        return $sent;
    }

    private function resolveEscalationRecipient(ServiceTicket $ticket, string $notify): ?\App\Models\User {
        if ($notify === '') {
            return $ticket->assignedTo;
        }
        if (ctype_digit($notify)) {
            return \App\Models\User::query()->withoutGlobalScopes()
                ->where('organization_id', $ticket->organization_id)
                ->find((int) $notify);
        }

        return \App\Models\User::query()->withoutGlobalScopes()
            ->where('organization_id', $ticket->organization_id)
            ->whereNull('deactivated_at')
            ->role($notify)
            ->orderBy('id')
            ->first();
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function slaPayload(ServiceTicket $ticket, string $messageKey): array {
        return [
            'title' => trim($ticket->ticket_no . ' — ' . $ticket->title, ' —'),
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $ticket->resolution_due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => ['date' => $ticket->resolution_due_at?->toIso8601String() ?? '–'],
            'url' => route('service-tickets.show', $ticket),
            'due_at' => $ticket->resolution_due_at,
        ];
    }
}
