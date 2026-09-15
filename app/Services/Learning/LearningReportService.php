<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\{LearningCourseStatus, LearningEnrollmentStatus, LearningProgressStatus};
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningQuizAttempt, LearningUnitProgress};
use App\Models\Organization;

/**
 * Kursanalyse (Feature 149, MVP-747).
 *
 * Zwei Grundsätze aus dem Konzept (Abschnitt 26) stecken hier:
 *
 *  1. **Aggregation statt Personenprofil.** Diese Auswertung zeigt Quoten
 *     und Abbruchpunkte je Kurs — nicht, wer wie lange auf welcher Seite
 *     war. Personenbezogene Fortschritte gehören ins Betreuer-Cockpit, wo
 *     sie an ein Recht und einen Zuständigkeitsbereich gebunden sind.
 *  2. **Kleine Gruppen werden nicht ausgewiesen.** Unter der Mindestgröße
 *     ließe sich von der Quote auf einzelne Personen zurückrechnen.
 */
class LearningReportService {
    /** Mindestzahl Einschreibungen, ab der eine Quote ausgewiesen wird. */
    public const MIN_GROUP = 5;

    /**
     * @param  list<int>|null  $courseIds  Trainer-Scoping (MVP-786): nur diese Kurse
     * @return list<array{course: LearningCourse, enrolled: int, completed: int, rate: float|null, suppressed: bool}>
     */
    public function courseCompletion(Organization $organization, ?array $courseIds = null): array {
        $courses = LearningCourse::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [LearningCourseStatus::Released->value, LearningCourseStatus::Archived->value])
            ->when($courseIds !== null, fn ($q) => $q->whereIn('id', $courseIds))
            ->orderBy('title')
            ->get();

        $counts = LearningEnrollment::query()
            ->selectRaw('learning_course_id, status, COUNT(*) as total')
            ->where('organization_id', $organization->id)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('learning_course_id', $courseIds))
            ->groupBy('learning_course_id', 'status')
            ->get()
            ->groupBy('learning_course_id');

        $rows = [];
        foreach ($courses as $course) {
            $byStatus = $counts->get($course->id, collect());
            $enrolled = (int) $byStatus->sum('total');
            $completed = (int) $byStatus
                ->where('status', LearningEnrollmentStatus::Completed->value)
                ->sum('total');

            $suppressed = $enrolled > 0 && $enrolled < self::MIN_GROUP;

            $rows[] = [
                'course' => $course,
                'enrolled' => $enrolled,
                'completed' => $completed,
                // Unter der Mindestgröße keine Quote — sonst ließe sich auf
                // einzelne Personen zurückrechnen.
                'rate' => ($enrolled >= self::MIN_GROUP) ? round($completed / $enrolled * 100, 1) : null,
                'suppressed' => $suppressed,
            ];
        }

        return $rows;
    }

    /**
     * Abbruchpunkte: die Einheit, an der am häufigsten Schluss ist —
     * begonnen, aber nicht abgeschlossen.
     *
     * @param  list<int>|null  $courseIds  Trainer-Scoping (MVP-786): nur diese Kurse
     * @return list<array{unit_title: string, course_title: string, started: int, completed: int, drop: int}>
     */
    public function dropOffPoints(Organization $organization, int $limit = 10, ?array $courseIds = null): array {
        $rows = LearningUnitProgress::query()
            ->selectRaw('learning_unit_id, status, COUNT(*) as total')
            ->where('organization_id', $organization->id)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('learning_unit_id', \App\Models\Learning\LearningUnit::query()->whereIn('learning_course_id', $courseIds)->select('id')))
            ->groupBy('learning_unit_id', 'status')
            ->get()
            ->groupBy('learning_unit_id');

        $units = \App\Models\Learning\LearningUnit::query()
            ->with('course')
            ->whereIn('id', $rows->keys())
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($rows as $unitId => $group) {
            $unit = $units->get($unitId);

            if ($unit === null) {
                continue;
            }

            $started = (int) $group->sum('total');
            $completed = (int) $group->where('status', LearningProgressStatus::Completed->value)->sum('total');
            $drop = $started - $completed;

            if ($drop <= 0) {
                continue;
            }

            $result[] = [
                'unit_title' => (string) $unit->title,
                'course_title' => (string) ($unit->course->title ?? ''),
                'started' => $started,
                'completed' => $completed,
                'drop' => $drop,
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['drop'] <=> $a['drop']);

        return array_slice($result, 0, max(1, $limit));
    }

    /**
     * Fragen-Auffälligkeiten: hohe Fehlerquote deutet auf eine unklare
     * Frage hin, nicht auf schlechte Lernende — deshalb steht diese Zahl
     * hier und nicht in einer Personenauswertung.
     *
     * @param  list<int>|null  $courseIds  Trainer-Scoping (MVP-786): nur diese Kurse
     * @return list<array{prompt: string, answered: int, correct: int, error_rate: float}>
     */
    public function questionDifficulty(Organization $organization, int $limit = 10, ?array $courseIds = null): array {
        $answers = \App\Models\Learning\LearningAnswer::query()
            ->selectRaw('learning_question_id, COUNT(*) as answered, SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct')
            ->where('organization_id', $organization->id)
            ->when($courseIds !== null, fn ($q) => $q->whereIn('learning_quiz_attempt_id', LearningQuizAttempt::query()
                ->whereIn('learning_enrollment_id', LearningEnrollment::query()->whereIn('learning_course_id', $courseIds)->select('id'))
                ->select('id')))
            ->whereNotNull('is_correct')
            ->groupBy('learning_question_id')
            ->get();

        $questions = \App\Models\Learning\LearningQuestion::query()
            ->whereIn('id', $answers->pluck('learning_question_id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($answers as $row) {
            $question = $questions->get($row->getAttribute('learning_question_id'));
            // Aggregat aus dem SELECT — nicht als Modell-Attribut deklariert.
            $answered = (int) $row->getAttribute('answered');

            if ($question === null || $answered < self::MIN_GROUP) {
                continue;
            }

            $correct = (int) $row->getAttribute('correct');
            $rows[] = [
                'prompt' => (string) $question->prompt,
                'answered' => $answered,
                'correct' => $correct,
                'error_rate' => round(($answered - $correct) / $answered * 100, 1),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['error_rate'] <=> $a['error_rate']);

        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * Statistik einer Prüfung (MVP-785): Versuche, Bestehensquote, Zeitbedarf
     * und Quoten je Frage — Quoten erst ab MIN_GROUP bewerteten Antworten,
     * sonst ließe sich auf Einzelne zurückrechnen.
     *
     * @return array{attempts: int, submitted: int, passed: int, pass_rate: float|null, median_seconds: int|null, questions: list<array{prompt: string, answered: int, correct: int|null, error_rate: float|null}>}
     */
    public function quizStatistics(\App\Models\Learning\LearningQuiz $quiz): array {
        $attempts = \App\Models\Learning\LearningQuizAttempt::query()
            ->where('learning_quiz_id', $quiz->id)
            ->get(['id', 'started_at', 'submitted_at', 'passed']);
        $submitted = $attempts->whereNotNull('submitted_at');
        $passed = $submitted->where('passed', true)->count();

        $durations = $submitted
            ->map(fn ($a): int => max(0, (int) $a->started_at?->diffInSeconds($a->submitted_at, true)))
            ->sort()
            ->values();
        $median = $durations->isEmpty() ? null : (int) $durations->get(intdiv($durations->count(), 2));

        $rows = \App\Models\Learning\LearningAnswer::query()
            ->selectRaw('learning_question_id, COUNT(*) as answered, SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct')
            ->whereIn('learning_quiz_attempt_id', $attempts->pluck('id'))
            ->whereNotNull('is_correct')
            ->groupBy('learning_question_id')
            ->get();
        $questions = \App\Models\Learning\LearningQuestion::query()
            ->whereIn('id', $rows->pluck('learning_question_id'))
            ->get()
            ->keyBy('id');

        $perQuestion = [];
        foreach ($rows as $row) {
            $question = $questions->get($row->getAttribute('learning_question_id'));
            if ($question === null) {
                continue;
            }
            $answered = (int) $row->getAttribute('answered');
            $correct = (int) $row->getAttribute('correct');
            $enough = $answered >= self::MIN_GROUP;
            $perQuestion[] = [
                'prompt' => (string) ($question->title ?? $question->prompt),
                'answered' => $answered,
                'correct' => $enough ? $correct : null,
                'error_rate' => $enough ? round(($answered - $correct) / $answered * 100, 1) : null,
            ];
        }
        usort($perQuestion, static fn (array $a, array $b): int => ($b['error_rate'] ?? -1) <=> ($a['error_rate'] ?? -1));

        return [
            'attempts' => $attempts->count(),
            'submitted' => $submitted->count(),
            'passed' => $passed,
            'pass_rate' => $submitted->count() >= self::MIN_GROUP ? round($passed / $submitted->count() * 100, 1) : null,
            'median_seconds' => $median,
            'questions' => $perQuestion,
        ];
    }

    /**
     * Kennzahlen für die Kopfzeile der Auswertung.
     *
     * @param  list<int>|null  $courseIds  Trainer-Scoping (MVP-786): nur diese Kurse
     * @return array{courses: int, enrollments: int, completed: int, attempts: int}
     */
    public function summary(Organization $organization, ?array $courseIds = null): array {
        $enrollmentIds = $courseIds !== null
            ? LearningEnrollment::query()->whereIn('learning_course_id', $courseIds)->select('id')
            : null;

        return [
            'courses' => LearningCourse::query()->where('organization_id', $organization->id)
                ->when($courseIds !== null, fn ($q) => $q->whereIn('id', $courseIds))->count(),
            'enrollments' => LearningEnrollment::query()->where('organization_id', $organization->id)
                ->when($courseIds !== null, fn ($q) => $q->whereIn('learning_course_id', $courseIds))->count(),
            'completed' => LearningEnrollment::query()
                ->where('organization_id', $organization->id)
                ->when($courseIds !== null, fn ($q) => $q->whereIn('learning_course_id', $courseIds))
                ->where('status', LearningEnrollmentStatus::Completed->value)
                ->count(),
            'attempts' => LearningQuizAttempt::query()
                ->where('organization_id', $organization->id)
                ->when($enrollmentIds !== null, fn ($q) => $q->whereIn('learning_enrollment_id', $enrollmentIds))
                ->whereNotNull('submitted_at')
                ->count(),
        ];
    }
}
