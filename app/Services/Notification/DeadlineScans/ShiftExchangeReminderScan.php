<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeReminderScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Shift\ShiftExchangeStatus;
use App\Models\{ShiftExchange, User};
use App\Services\Notification\NotificationDispatcher;

/**
 * Schichttausch (Feature 007): noch offene Tausch-Anträge (requested/accepted)
 * erinnern die Teamleitung an die ausstehende Freigabe. Dedup über das
 * notification_dispatch_log pro Antrag (1× pro Tag genügt; das Re-Notify
 * greift erst, wenn der Antrag entschieden und ein neuer angelegt wird).
 */
class ShiftExchangeReminderScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'shift_exchanges';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        return $this->runScan($dispatcher, [
            'affected' => fn(ShiftExchange $exchange): ?User => $exchange->targetUser,
            'due' => [
                'query' => fn() => ShiftExchange::query()
                    ->whereIn('status', [
                        ShiftExchangeStatus::Requested->value,
                        ShiftExchangeStatus::Accepted->value,
                    ])
                    ->with(['scheduledShift', 'targetUser']),
                'event' => NotificationEvent::ShiftExchangeRequested,
                'payload' => fn(ShiftExchange $exchange): array => $this->shiftExchangePayload($exchange),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null} */
    private function shiftExchangePayload(ShiftExchange $exchange): array {
        return [
            'title' => (string) __('schedule.exchange.notification_request_title'),
            'title_key' => 'schedule.exchange.notification_request_title',
            'message' => (string) __('schedule.exchange.notification_pending_message', [
                'date' => $exchange->scheduledShift?->date?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'schedule.exchange.notification_pending_message',
            'message_params' => ['date' => $exchange->scheduledShift?->date?->toDateString() ?? '–'],
            'url' => $this->safeRoute('schedule.exchanges.index'),
        ];
    }
}
