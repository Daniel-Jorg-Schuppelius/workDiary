<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAssignmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningSubmissionStatus;
use App\Models\Learning\{LearningAssignment, LearningEnrollment, LearningSubmission};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aufgaben, Abgaben und Bewertung (Feature 149, MVP-739) — einzige
 * Schreibstelle.
 *
 * Die Regeln:
 *  1. Bewertet wird gegen eine **eingefrorene Rubrik**; eine später
 *     geänderte Rubrik deutet alte Bewertungen nicht um.
 *  2. **Vier-Augen** heißt vier Augen: die Zweitbewertung darf nicht von
 *     der Person stammen, die erstbewertet hat.
 *  3. Eine Rückgabe zur Überarbeitung ist kein Scheitern — die Person darf
 *     erneut abgeben, der Zähler läuft mit.
 *  4. Bestanden ⇒ die Lerneinheit gilt über den regulären Weg als
 *     abgeschlossen (der Kursabschluss entsteht an genau einer Stelle).
 */
class LearningAssignmentService {
    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /** Entwurf holen oder anlegen — die Abgabe gehört zur Einschreibung. */
    public function draftFor(LearningEnrollment $enrollment, LearningAssignment $assignment): LearningSubmission {
        return LearningSubmission::query()->firstOrCreate(
            [
                'learning_assignment_id' => $assignment->id,
                'learning_enrollment_id' => $enrollment->id,
            ],
            [
                'organization_id' => $enrollment->organization_id,
                'status' => LearningSubmissionStatus::Draft->value,
                // Explizit, nicht über den DB-Default: ein frisch erzeugtes
                // Model kennt Defaults der Datenbank nicht, und der Zähler
                // wird direkt danach gelesen.
                'attempt_no' => 1,
            ]
        );
    }

    public function submit(LearningEnrollment $enrollment, LearningAssignment $assignment, ?string $body, ?Carbon $now = null): LearningSubmission {
        $now ??= Carbon::now();

        if ($enrollment->status->isFinal()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.enrollment_closed'),
            ]);
        }

        $submission = $this->draftFor($enrollment, $assignment);

        if (! $submission->status->allowsSubmission()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.submission_locked'),
            ]);
        }

        if ($assignment->requiresText() && trim((string) $body) === '') {
            throw ValidationException::withMessages([
                'body' => (string) __('learning.errors.submission_text_required'),
            ]);
        }

        // Verlangt die Aufgabe eine Datei, ist eine Abgabe ohne Anhang keine
        // Abgabe — sonst landet sie als „bewerten" im Cockpit und die
        // bewertende Person sucht die Datei.
        if ($assignment->requiresFile() && $submission->attachments()->count() === 0) {
            throw ValidationException::withMessages([
                'file' => (string) __('learning.errors.submission_file_required'),
            ]);
        }

        $wasReturned = $submission->status === LearningSubmissionStatus::Returned;

        $submission->update([
            'status' => LearningSubmissionStatus::Submitted->value,
            'body' => $body,
            'submitted_at' => $now,
            // Eine erneute Abgabe nach Rückgabe zählt als weiterer Anlauf.
            'attempt_no' => $wasReturned ? (int) $submission->attempt_no + 1 : max(1, (int) $submission->attempt_no),
        ]);

        return $submission->refresh();
    }

    /** Zur Überarbeitung zurückgeben — mit Begründung, sonst hilft es niemandem. */
    public function returnForRevision(LearningSubmission $submission, string $feedback, ?User $actor = null): LearningSubmission {
        if (! $submission->isPending()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.submission_not_pending'),
            ]);
        }

        if (trim($feedback) === '') {
            throw ValidationException::withMessages([
                'feedback' => (string) __('learning.errors.feedback_required'),
            ]);
        }

        $submission->update([
            'status' => LearningSubmissionStatus::Returned->value,
            'feedback' => $feedback,
            'graded_by_user_id' => $actor?->id,
        ]);

        return $submission->refresh();
    }

    /**
     * Bewerten. Entweder über die Rubrik (Punkte je Kriterium) oder als
     * Gesamtpunktzahl, wenn keine Rubrik gepflegt ist.
     *
     * @param  array<string, int>  $rubricScores
     */
    public function grade(
        LearningSubmission $submission,
        array $rubricScores = [],
        ?int $totalPoints = null,
        ?string $feedback = null,
        ?User $actor = null,
        ?Carbon $now = null,
    ): LearningSubmission {
        $now ??= Carbon::now();
        $assignment = $submission->assignment;

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'assignment' => (string) __('learning.errors.submission_without_assignment'),
            ]);
        }

        if ($submission->status === LearningSubmissionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.submission_not_pending'),
            ]);
        }

        $criteria = $assignment->criteria();
        $points = $criteria !== []
            ? $this->pointsFromRubric($criteria, $rubricScores)
            : max(0, (int) ($totalPoints ?? 0));

        $max = max(1, $assignment->points);
        $points = min($points, $assignment->points);
        $percent = (int) round($points / $max * 100);

        return DB::transaction(function () use ($submission, $assignment, $criteria, $rubricScores, $points, $percent, $feedback, $actor, $now): LearningSubmission {
            $submission->update([
                'status' => LearningSubmissionStatus::Graded->value,
                'points_awarded' => $points,
                'score_percent' => $percent,
                'passed' => $percent >= $assignment->pass_percent,
                'feedback' => $feedback ?? $submission->feedback,
                'graded_by_user_id' => $actor?->id,
                'graded_at' => $now,
                'rubric_scores' => $rubricScores !== [] ? $rubricScores : null,
                // Eingefroren: die Kriterien dieser Bewertung.
                'rubric_snapshot' => $criteria !== [] ? $criteria : null,
            ]);

            $this->completeUnitIfPassed($submission->refresh());

            return $submission;
        });
    }

    /**
     * Zweitbewertung (Vier-Augen). Sie bestätigt die Erstbewertung; erst
     * danach gilt sie als endgültig und kann die Einheit abschließen.
     */
    public function secondOpinion(LearningSubmission $submission, User $actor, ?Carbon $now = null): LearningSubmission {
        $assignment = $submission->assignment;

        if ($assignment === null || ! $assignment->requires_second_opinion) {
            throw ValidationException::withMessages([
                'assignment' => (string) __('learning.errors.second_opinion_not_required'),
            ]);
        }

        if ($submission->status !== LearningSubmissionStatus::Graded) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.second_opinion_needs_grade'),
            ]);
        }

        if ($submission->graded_by_user_id === $actor->id) {
            throw ValidationException::withMessages([
                'second_opinion' => (string) __('learning.errors.second_opinion_same_person'),
            ]);
        }

        $submission->update([
            'second_opinion_by_user_id' => $actor->id,
            'second_opinion_at' => $now ?? Carbon::now(),
        ]);

        $this->completeUnitIfPassed($submission->refresh());

        return $submission;
    }

    /**
     * Offene Bewertungen einer Organisation — die Arbeitsliste des
     * Betreuer-Cockpits.
     *
     * @return \Illuminate\Database\Eloquent\Builder<LearningSubmission>
     */
    public function pendingQuery() {
        return LearningSubmission::query()
            ->with(['assignment.unit.course', 'enrollment.user'])
            ->where('status', LearningSubmissionStatus::Submitted->value)
            ->orderBy('submitted_at');
    }

    /**
     * @param  list<array<string, mixed>>  $criteria
     * @param  array<string, int>  $scores
     */
    private function pointsFromRubric(array $criteria, array $scores): int {
        $sum = 0;
        foreach ($criteria as $criterion) {
            $key = (string) ($criterion['key'] ?? '');
            $max = max(0, (int) ($criterion['max_points'] ?? 0));
            $given = max(0, (int) ($scores[$key] ?? 0));
            $sum += min($given, $max);
        }

        return $sum;
    }

    /** Bestanden und endgültig ⇒ Einheit abschließen. */
    private function completeUnitIfPassed(LearningSubmission $submission): void {
        if ($submission->passed !== true || ! $submission->isFinal()) {
            return;
        }

        $unit = $submission->assignment?->unit;
        $enrollment = $submission->enrollment;

        if ($unit === null || $enrollment === null || $enrollment->status->isFinal()) {
            return;
        }

        $this->enrollments->completeUnit($enrollment, $unit);
    }
}
