<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueDeadlineScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Models\{OpenIssue, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Offene Punkte (MVP-018): fällige/überfällige Punkte an Bearbeiter
 * (Fallback Ersteller) + Eskalation.
 */
class OpenIssueDeadlineScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'open_issues';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $openStates = OpenIssueStatus::openValues();
        $now = Carbon::now();
        $dueDays = $options->dueDays;

        return $this->runScan($dispatcher, [
            'affected' => fn(OpenIssue $issue): ?User => $this->issueAffected($issue),
            'due' => [
                'query' => fn() => OpenIssue::query()
                    ->whereIn('status', $openStates)
                    ->whereNotNull('due_at')
                    ->where('due_at', '>', $now)
                    ->where('due_at', '<=', $now->copy()->addDays($dueDays)),
                'event' => NotificationEvent::OpenIssueDueSoon,
                'payload' => fn(OpenIssue $issue): array => $this->issuePayload($issue, 'due_soon'),
            ],
            'overdue' => [
                'query' => fn() => OpenIssue::query()
                    ->whereIn('status', $openStates)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<=', $now),
                'event' => NotificationEvent::OpenIssueOverdue,
                'payload' => fn(OpenIssue $issue): array => $this->issuePayload($issue, 'overdue'),
            ],
        ]);
    }

    private function issueAffected(OpenIssue $issue): ?User {
        return $issue->assignee ?? $issue->creator;
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function issuePayload(OpenIssue $issue, string $messageKey): array {
        return [
            'title' => (string) $issue->title,
            'message' => (string) __('notification.message.' . $messageKey, [
                'date' => $issue->due_at?->format('d.m.Y H:i') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => ['date' => $issue->due_at?->toIso8601String() ?? '–'],
            'url' => \App\Support\NotificationLinks::openIssueUrl($issue),
            'due_at' => $issue->due_at,
        ];
    }
}
