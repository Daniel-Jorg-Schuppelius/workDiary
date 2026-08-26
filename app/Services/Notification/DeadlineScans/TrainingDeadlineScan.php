<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingDeadlineScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{Organization, User};
use App\Models\Training\{TrainingAssignment, TrainingCourse, TrainingRequirement};
use App\Services\Notification\NotificationDispatcher;
use App\Services\Training\TrainingAssignmentService;
use Illuminate\Support\Carbon;

/**
 * Schulungsfristen (Feature 145): Soll-Einträge, deren Meldefenster
 * (`notify_from` = Fälligkeit minus kursindividuellem Vorlauf) erreicht
 * ist. Wie beim Arbeitsschutz-Register läuft alles über EINE Phase `due` —
 * fällig und überfällig feuern dasselbe Ereignis, Dedup über das
 * Dispatch-Log; die Eskalation an die Teamleitung steckt in den
 * Empfängerrollen des Ereignisses.
 *
 * Vor dem Prüfen zieht der Scan die Pflichtmatrix nach: neue Mitarbeitende
 * bekommen ihr Soll ohne zusätzlichen Scheduler-Job. Gesperrt wird nichts —
 * die Sperrwirkung bleibt beim Qualifikationsstatus (Feature 013).
 */
class TrainingDeadlineScan extends AbstractDeadlineScan {
    public function __construct(
        private readonly TrainingAssignmentService $assignments,
    ) {}

    public function key(): string {
        return 'training';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        unset($options); // Vorlauf steckt je Kurs in notify_from.

        $this->syncOrganizations();

        $today = Carbon::today()->toDateString();

        return $this->runScan($dispatcher, [
            'affected' => fn(TrainingAssignment $assignment): ?User => $assignment->user()->first(),
            'due' => [
                'query' => fn() => TrainingAssignment::query()->withoutGlobalScopes()
                    ->whereNotNull('due_at')
                    ->whereNotNull('notify_from')
                    ->where('notify_from', '<=', $today)
                    ->whereIn('training_course_id', TrainingCourse::query()->withoutGlobalScopes()
                        ->where('is_active', true)
                        ->select('id')),
                'event' => NotificationEvent::TrainingDue,
                'payload' => fn(TrainingAssignment $assignment): array => $this->payload($assignment),
            ],
        ]);
    }

    /** Pflichtmatrix je Organisation in Soll-Einträge überführen. */
    private function syncOrganizations(): void {
        $this->sumPerOrganization(
            TrainingRequirement::query()->withoutGlobalScopes()->where('is_active', true)->select('organization_id'),
            function (Organization $organization): int {
                $this->assignments->syncOrganization($organization);

                return 0;
            },
        );
    }

    /** @return array{title: string, message: string, url: string|null, due_at: Carbon|null} */
    private function payload(TrainingAssignment $assignment): array {
        $course = $assignment->course()->withoutGlobalScopes()->first();
        $title = $course instanceof TrainingCourse ? $course->title : (string) __('training.title.assignments');

        return [
            'title' => $title,
            'message' => (string) __('notification.message.training_due', [
                'course' => $title,
                'date' => $assignment->due_at?->format('d.m.Y') ?? '–',
            ]),
            'message_key' => 'notification.message.training_due',
            'message_params' => [
                'course' => $title,
                'date' => $assignment->due_at?->toDateString() ?? '–',
            ],
            'url' => $this->safeRoute('training.assignments.index'),
            'due_at' => $assignment->due_at,
        ];
    }
}
