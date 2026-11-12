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

namespace App\Services\ServiceTicket\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket, SlaContract};
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
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
                    if (! $this->moduleActiveFor($ticket->organization_id)) {
                        continue;
                    }
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

        return $sent + $this->scanOlaTargets($dispatcher);
    }

    /**
     * Internes Ziel (OLA, MVP-1548): überschreitet ein offenes Ticket das Ziel des
     * Queue-Teams, geht die Meldung einmal an die Teamleitung des OLA-Teams
     * (ohne sie an die Bearbeitung) und eskaliert nach den Benachrichtigungsregeln.
     * Kein Verletzungsregister — Feature 065 sagt für OLA Messung und Eskalation zu.
     */
    private function scanOlaTargets(NotificationDispatcher $dispatcher): int {
        $teamIds = SlaContract::query()->where('is_active', true)->where('is_ola', true)->whereNotNull('ola_team_id')->pluck('ola_team_id')->unique()->all();
        if ($teamIds === []) {
            return 0;
        }

        $sent = 0;
        ServiceTicket::query()
            ->open()
            ->whereNotNull('reported_at')
            ->whereIn('queue_id', ServiceQueue::query()->whereIn('team_id', $teamIds)->select('id'))
            ->with('queue.team')
            ->chunkById(200, function (Collection $tickets) use ($dispatcher, &$sent): void {
                /** @var Collection<int, ServiceTicket> $tickets */
                foreach ($tickets as $ticket) {
                    if (! $this->moduleActiveFor($ticket->organization_id)) {
                        continue;
                    }
                    $targets = $this->timer->olaTargets($ticket);
                    $breached = $targets === null ? null : match (true) {
                        $targets['resolution_status'] === \App\Enums\ServiceTicket\SlaStatus::Breached => $targets['resolution_due_at'],
                        $targets['reaction_status'] === \App\Enums\ServiceTicket\SlaStatus::Breached && $ticket->acknowledged_at === null => $targets['reaction_due_at'],
                        default => null,
                    };
                    $team = $ticket->queue?->team;
                    if ($breached === null || $team === null) {
                        continue;
                    }
                    $lead = $team->lead_user_id !== null
                        ? \App\Models\Platform\User::query()->where('organization_id', $ticket->organization_id)->find($team->lead_user_id)
                        : null;
                    $payload = [
                        'title' => trim($ticket->ticket_no . ' — ' . $ticket->title, ' —'),
                        'message' => (string) __('notification.message.sla_ola_breached', ['team' => $team->name, 'date' => $breached->format('d.m.Y H:i')]),
                        'message_key' => 'notification.message.sla_ola_breached',
                        'message_params' => ['team' => $team->name, 'date' => $breached->toIso8601String()],
                        'url' => route('service-tickets.show', $ticket),
                        'due_at' => $breached,
                    ];
                    $sent += $dispatcher->notify(NotificationEvent::SlaOlaBreached, $ticket, $lead ?? $ticket->assignedTo, $payload, dedup: true);
                    $sent += $dispatcher->escalateIfDue(NotificationEvent::SlaOlaBreached, $ticket, $payload);
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

    private function resolveEscalationRecipient(ServiceTicket $ticket, string $notify): ?\App\Models\Platform\User {
        if ($notify === '') {
            return $ticket->assignedTo;
        }
        if (ctype_digit($notify)) {
            return \App\Models\Platform\User::query()->withoutGlobalScopes()
                ->where('organization_id', $ticket->organization_id)
                ->find((int) $notify);
        }

        return \App\Models\Platform\User::query()->withoutGlobalScopes()
            ->where('organization_id', $ticket->organization_id)
            ->whereNull('deactivated_at')
            ->role(\App\Services\Org\OrganizationRoles::matching([$notify], (int) $ticket->organization_id))
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
