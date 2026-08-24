<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationExpiryScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{User, UserQualification};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Qualifikations-/Unterweisungsablauf (Feature 013): Mitarbeiter-
 * Qualifikationen mit gesetztem valid_until innerhalb des Vorlaufs
 * (--expiring-days, Default 30 Tage) melden. Empfänger ist die betroffene
 * Person (notify_affected), Default-Fallback die Rolle teamleitung
 * (NotificationEvent). Org-Kontext wird über den User aufgelöst. Dedup über
 * das notification_dispatch_log pro Pivot-Zeile (User × Qualifikation).
 */
class QualificationExpiryScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'qualifications';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        $expiringDays = $options->expiringDays;

        return $this->runScan($dispatcher, [
            'affected' => fn(UserQualification $assignment): ?User => $assignment->user,
            'require_affected' => true,
            'due' => [
                'query' => fn() => UserQualification::query()
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '>=', $today)
                    ->whereDate('valid_until', '<=', $today->copy()->addDays($expiringDays))
                    ->with(['user', 'qualification']),
                'event' => NotificationEvent::QualificationExpiring,
                'payload' => fn(UserQualification $assignment): array => $this->qualificationPayload($assignment),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function qualificationPayload(UserQualification $assignment): array {
        $name = (string) ($assignment->qualification->name ?? '');

        return [
            'title' => $name,
            'message' => (string) __('notification.message.qualification_expiring', [
                'date' => $assignment->valid_until?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.qualification_expiring',
            'message_params' => ['date' => $assignment->valid_until?->toDateString() ?? '–'],
            'url' => route('reports.qualifications'),
            'due_at' => $assignment->valid_until,
        ];
    }
}
