<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskFollowupScans.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{Problem, ServiceTicket, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Helpdesk-Wiedervorlagen (Feature 065): abgelaufene Ticket-Wartefristen und
 * fällige Wirksamkeitsprüfungen im Problem-Management.
 */
class HelpdeskFollowupScans extends AbstractDeadlineScan {
    public function key(): string {
        return 'helpdesk_followups';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->scanWaitingTickets($dispatcher) + $this->scanProblemEffectiveness($dispatcher);
    }

    /**
     * Wiedervorlagen (Feature 065, P3): wartende Tickets mit überschrittener
     * wait_until → Notification an wait_owner (Fallback Bearbeiter).
     */
    private function scanWaitingTickets(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(ServiceTicket $ticket): ?User => $ticket->wait_owner_id !== null
                ? User::query()->find($ticket->wait_owner_id)
                : $ticket->assignedTo,
            'due' => [
                'query' => fn() => ServiceTicket::query()
                    ->whereIn('status', [
                        ServiceTicketStatus::WaitingCustomer->value,
                        ServiceTicketStatus::WaitingExternal->value,
                        ServiceTicketStatus::Paused->value,
                    ])
                    ->whereNotNull('wait_until')
                    ->where('wait_until', '<=', Carbon::now()),
                'event' => NotificationEvent::TicketWaitingExpired,
                'payload' => fn(ServiceTicket $ticket): array => [
                    'title' => (string) __('Wiedervorlage fällig: Ticket :no', ['no' => $ticket->ticket_no]),
                    'title_key' => 'Wiedervorlage fällig: Ticket :no',
                    'title_params' => ['no' => $ticket->ticket_no],
                    'body' => (string) ($ticket->wait_reason ?? $ticket->title),
                    'url' => route('service-tickets.show', $ticket),
                    'due_at' => $ticket->wait_until,
                ],
            ],
        ]);
    }

    /**
     * Problem-Management (Feature 065, MVP-156): gelöste/Known-Error-Probleme
     * mit überschrittener Wirksamkeitsfrist ohne Prüfung melden (Empfänger
     * Owner, Fallback teamleitung) + Eskalation.
     */
    private function scanProblemEffectiveness(NotificationDispatcher $dispatcher): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(Problem $problem): ?User => $problem->owner()->first(),
            'overdue' => [
                'query' => fn() => Problem::query()
                    ->whereIn('status', ['resolved', 'known_error'])
                    ->whereNotNull('effectiveness_check_due_at')
                    ->where('effectiveness_check_due_at', '<=', Carbon::now())
                    ->whereNull('effectiveness_checked_at'),
                'event' => NotificationEvent::ProblemEffectivenessDue,
                'payload' => fn(Problem $problem): array => $this->problemEffectivenessPayload($problem),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function problemEffectivenessPayload(Problem $problem): array {
        return [
            'title' => (string) $problem->title,
            'message' => (string) __('Wirksamkeitsprüfung fällig seit :date.', [
                'date' => $problem->effectiveness_check_due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'Wirksamkeitsprüfung fällig seit :date.',
            'message_params' => ['date' => $problem->effectiveness_check_due_at?->toIso8601String() ?? '–'],
            'url' => route('servicedesk.problems.show', $problem),
            'due_at' => $problem->effectiveness_check_due_at,
        ];
    }
}
