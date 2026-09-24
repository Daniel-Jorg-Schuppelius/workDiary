<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDueScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\DeadlineScans;

use App\Enums\Learning\LearningEnrollmentStatus;
use App\Enums\Notification\NotificationEvent;
use App\Models\Learning\LearningEnrollment;
use App\Models\Platform\User;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Fristen der Lernplattform (Feature 149, MVP-780): offene Einschreibungen
 * mit `due_at` innerhalb des Vorlaufs (`dueSoon`) und mit überschrittener
 * Frist (`overdue`, mit Eskalationsleiter). Dedup über das Dispatch-Log —
 * je Einschreibung und Phase genau eine Meldung.
 *
 * Nur Personen mit Konto: Externe ohne Konto erreichen die Org-Regeln
 * nicht; sie bekommen ihre Post über den Einstiegslink.
 */
class LearningDueScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'learning';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($options->dueDays)->toDateString();

        return $this->runScan($dispatcher, [
            'affected' => fn (LearningEnrollment $enrollment): ?User => $enrollment->user()->first(),
            'require_affected' => true,
            'due' => [
                'query' => fn (): Builder => $this->openWithDueDate()
                    ->where('due_at', '>=', $today->toDateString())
                    ->where('due_at', '<=', $horizon),
                'event' => NotificationEvent::LearningDueSoon,
                'payload' => fn (LearningEnrollment $enrollment): array => $this->payload($enrollment, 'learning_due_soon'),
            ],
            'overdue' => [
                'query' => fn (): Builder => $this->openWithDueDate()
                    ->where('due_at', '<', $today->toDateString()),
                'event' => NotificationEvent::LearningOverdue,
                'payload' => fn (LearningEnrollment $enrollment): array => $this->payload($enrollment, 'learning_overdue'),
            ],
        ]);
    }

    /**
     * @return Builder<LearningEnrollment>
     */
    private function openWithDueDate(): Builder {
        return LearningEnrollment::query()
            ->withoutGlobalScopes()
            ->whereNotNull('due_at')
            ->whereNotNull('user_id')
            ->whereIn('status', [LearningEnrollmentStatus::Assigned->value, LearningEnrollmentStatus::InProgress->value]);
    }

    /** @return array{title: string, message: string, message_key: string, message_params: array<string, string>, url: string|null, due_at: Carbon|null} */
    private function payload(LearningEnrollment $enrollment, string $messageKey): array {
        $course = $enrollment->course()->withoutGlobalScopes()->first();
        $title = $course !== null ? (string) $course->title : (string) __('learning.title.my');

        return [
            'title' => $title,
            'message' => (string) __('notification.message.' . $messageKey, [
                'course' => $title,
                'date' => $enrollment->due_at?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => [
                'course' => $title,
                'date' => $enrollment->due_at?->toDateString() ?? '–',
            ],
            'url' => $this->safeEnrollmentRoute($enrollment),
            'due_at' => $enrollment->due_at,
        ];
    }

    private function safeEnrollmentRoute(LearningEnrollment $enrollment): ?string {
        try {
            return route('learning.my.show', $enrollment);
        } catch (\Throwable) {
            return null;
        }
    }
}
