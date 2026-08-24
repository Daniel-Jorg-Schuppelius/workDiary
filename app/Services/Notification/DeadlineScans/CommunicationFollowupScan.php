<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationFollowupScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{CommunicationNote, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Wiedervorlagen aus Kommunikationsnotizen (MVP-018): fällige/überfällige
 * next_action an den Zuständigen (Fallback Ersteller) + Eskalation.
 */
class CommunicationFollowupScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'communication_followups';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $now = Carbon::now();
        $dueDays = $options->dueDays;
        $pending = static fn() => CommunicationNote::query()
            ->whereNotNull('next_action_due_at')
            ->whereNull('next_action_completed_at');

        return $this->runScan($dispatcher, [
            'affected' => fn(CommunicationNote $note): ?User => $this->noteAffected($note),
            'due' => [
                'query' => fn() => $pending()
                    ->where('next_action_due_at', '>', $now)
                    ->where('next_action_due_at', '<=', $now->copy()->addDays($dueDays)),
                'event' => NotificationEvent::CommunicationFollowupDueSoon,
                'payload' => fn(CommunicationNote $note): array => $this->notePayload($note, 'followup_due_soon'),
            ],
            'overdue' => [
                'query' => fn() => $pending()->where('next_action_due_at', '<=', $now),
                'event' => NotificationEvent::CommunicationFollowupOverdue,
                'payload' => fn(CommunicationNote $note): array => $this->notePayload($note, 'followup_overdue'),
            ],
        ]);
    }

    private function noteAffected(CommunicationNote $note): ?User {
        return $note->getAttribute('next_action_user_id') !== null
            ? User::query()->find((int) $note->getAttribute('next_action_user_id'))
            : User::query()->find((int) $note->getAttribute('created_by_user_id'));
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function notePayload(CommunicationNote $note, string $messageKey): array {
        return [
            'title' => (string) ($note->getAttribute('next_action') ?: $note->getAttribute('subject') ?: __('notification.message.followup_fallback_title')),
            'title_key' => ($note->getAttribute('next_action') ?: $note->getAttribute('subject')) ? null : 'notification.message.followup_fallback_title',
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $note->next_action_due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => ['date' => $note->next_action_due_at?->toIso8601String() ?? '–'],
            'url' => null,
            'due_at' => $note->next_action_due_at,
        ];
    }
}
