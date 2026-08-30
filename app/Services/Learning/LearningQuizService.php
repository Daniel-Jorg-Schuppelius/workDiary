<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuizService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningAnswer, LearningEnrollment, LearningQuestion, LearningQuiz, LearningQuizAttempt};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prüfungsversuche (Feature 149, MVP-738) — einzige Schreibstelle.
 *
 * Die Regeln, die hier stecken:
 *  1. Ein Versuch **friert die Fragen ein**. Bewertet wird immer gegen den
 *     Snapshot, nie gegen die aktuelle Frage — sonst änderte eine spätere
 *     Korrektur rückwirkend alte Ergebnisse.
 *  2. **Versuchsgrenze und Sperrfrist** werden vor dem Start geprüft, nicht
 *     erst beim Abgeben.
 *  3. Ein abgegebener Versuch wird **nicht überschrieben**; eine Korrektur
 *     ist additiv und trägt Begründung, Person und Zeitpunkt.
 *  4. Bestanden ⇒ die zugehörige Lerneinheit gilt als abgeschlossen (der
 *     Weg dorthin bleibt der {@see LearningEnrollmentService}).
 */
class LearningQuizService {
    public function __construct(
        private readonly LearningAnswerGrader $grader,
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /**
     * @param  array<string, mixed>  $context  client_ip, user_agent
     */
    public function startAttempt(LearningEnrollment $enrollment, LearningQuiz $quiz, array $context = [], ?Carbon $now = null): LearningQuizAttempt {
        $now ??= Carbon::now();

        if ($enrollment->status->isFinal()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.enrollment_closed'),
            ]);
        }

        $open = $this->openAttempt($enrollment, $quiz);
        if ($open !== null) {
            return $open;
        }

        $previous = $this->attemptsQuery($enrollment, $quiz)->orderByDesc('attempt_no')->first();
        $count = $this->attemptsQuery($enrollment, $quiz)->count();

        if (! $quiz->allowsUnlimitedAttempts() && $count >= $quiz->max_attempts) {
            throw ValidationException::withMessages([
                'attempts' => (string) __('learning.errors.attempts_exhausted'),
            ]);
        }

        if ($previous !== null && $quiz->retry_wait_hours > 0) {
            $ready = ($previous->submitted_at ?? $previous->started_at)?->copy()->addHours($quiz->retry_wait_hours);
            if ($ready !== null && $ready->greaterThan($now)) {
                throw ValidationException::withMessages([
                    'attempts' => (string) __('learning.errors.retry_wait', ['time' => $ready->translatedFormat('d.m.Y H:i')]),
                ]);
            }
        }

        return DB::transaction(function () use ($enrollment, $quiz, $context, $now, $count): LearningQuizAttempt {
            $snapshot = $this->buildSnapshot($quiz);

            if ($snapshot === []) {
                throw ValidationException::withMessages([
                    'questions' => (string) __('learning.errors.quiz_without_questions'),
                ]);
            }

            return LearningQuizAttempt::query()->create([
                'organization_id' => $enrollment->organization_id,
                'learning_quiz_id' => $quiz->id,
                'learning_enrollment_id' => $enrollment->id,
                'attempt_no' => $count + 1,
                'started_at' => $now,
                'expires_at' => $quiz->time_limit_minutes !== null
                    ? $now->copy()->addMinutes($quiz->time_limit_minutes)
                    : null,
                'questions_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'max_points' => array_sum(array_map(static fn (array $q): int => (int) $q['points'], $snapshot)),
                'client_ip' => $context['client_ip'] ?? null,
                'user_agent' => isset($context['user_agent']) ? mb_substr((string) $context['user_agent'], 0, 255) : null,
            ]);
        });
    }

    /**
     * Versuch abgeben und automatisch bewerten. Ein abgelaufenes Zeitlimit
     * verhindert die Abgabe NICHT — gewertet wird der Ist-Stand, sonst
     * ginge Arbeit der lernenden Person verloren.
     *
     * @param  array<int, array<string, mixed>>  $answers  Frage-ID → Antwort
     */
    public function submitAttempt(LearningQuizAttempt $attempt, array $answers, ?Carbon $now = null): LearningQuizAttempt {
        $now ??= Carbon::now();

        if (! $attempt->isOpen()) {
            throw ValidationException::withMessages([
                'attempt' => (string) __('learning.errors.attempt_closed'),
            ]);
        }

        return DB::transaction(function () use ($attempt, $answers, $now): LearningQuizAttempt {
            $questions = $attempt->questions();
            $score = 0;
            $needsManualGrading = false;

            foreach ($questions as $question) {
                $questionId = (int) ($question['id'] ?? 0);
                $payload = $answers[$questionId] ?? null;
                $payload = is_array($payload) ? $payload : null;

                $result = $this->grader->grade($question, $payload);
                $score += $result['points'];
                $needsManualGrading = $needsManualGrading || $result['correct'] === null;

                LearningAnswer::query()->updateOrCreate(
                    [
                        'learning_quiz_attempt_id' => $attempt->id,
                        'learning_question_id' => $questionId,
                    ],
                    [
                        'organization_id' => $attempt->organization_id,
                        'payload' => $payload,
                        'is_correct' => $result['correct'],
                        'points_awarded' => $result['points'],
                    ]
                );
            }

            $max = max(1, (int) $attempt->max_points);
            $percent = (int) round($score / $max * 100);
            $passPercent = $attempt->quiz->pass_percent ?? 80;

            $attempt->update([
                'submitted_at' => $now,
                'score_points' => $score,
                'score_percent' => $percent,
                // Solange ein Aufsatz auf Bewertung wartet, steht das
                // Gesamtergebnis noch nicht fest.
                'passed' => $needsManualGrading ? null : $percent >= $passPercent,
            ]);

            $this->completeUnitIfPassed($attempt->refresh());

            return $attempt;
        });
    }

    /**
     * Manuelle Bewertung bzw. Korrektur einer Antwort — additiv, mit
     * Begründung und Person. Der ursprüngliche Automatikwert bleibt stehen.
     */
    public function correctAnswer(LearningAnswer $answer, int $points, ?string $note, ?User $actor = null): LearningAnswer {
        return DB::transaction(function () use ($answer, $points, $note, $actor): LearningAnswer {
            $answer->update([
                'corrected_points' => max(0, $points),
                'correction_note' => $note,
                'graded_by_user_id' => $actor?->id,
                'graded_at' => Carbon::now(),
            ]);

            $this->recalculate($answer->attempt);

            return $answer->refresh();
        });
    }

    /** Ergebnis eines Versuchs neu rechnen (nach einer Korrektur). */
    public function recalculate(?LearningQuizAttempt $attempt): void {
        if ($attempt === null) {
            return;
        }

        $answers = $attempt->answers()->get();
        $score = 0;
        $open = false;

        foreach ($answers as $answer) {
            $score += $answer->effectivePoints();
            if ($answer->is_correct === null && $answer->corrected_points === null) {
                $open = true;
            }
        }

        $max = max(1, (int) $attempt->max_points);
        $percent = (int) round($score / $max * 100);

        $attempt->update([
            'score_points' => $score,
            'score_percent' => $percent,
            'passed' => $open ? null : $percent >= ($attempt->quiz->pass_percent ?? 80),
        ]);

        $this->completeUnitIfPassed($attempt->refresh());
    }

    public function openAttempt(LearningEnrollment $enrollment, LearningQuiz $quiz): ?LearningQuizAttempt {
        return $this->attemptsQuery($enrollment, $quiz)->whereNull('submitted_at')->latest('id')->first();
    }

    /**
     * Bestandene Prüfung schließt ihre Lerneinheit ab — über den regulären
     * Weg, damit der Kursabschluss an genau einer Stelle entsteht.
     */
    private function completeUnitIfPassed(LearningQuizAttempt $attempt): void {
        if ($attempt->passed !== true) {
            return;
        }

        $unit = $attempt->quiz?->unit;
        $enrollment = $attempt->enrollment;

        if ($unit === null || $enrollment === null || $enrollment->status->isFinal()) {
            return;
        }

        $this->enrollments->completeUnit($enrollment, $unit);
    }

    /**
     * Fragenauswahl und Mischung für einen Versuch.
     *
     * @return list<array<string, mixed>>
     */
    private function buildSnapshot(LearningQuiz $quiz): array {
        $questions = $quiz->questions()->with('options')->get();

        if ($quiz->questions_per_attempt !== null && $quiz->questions_per_attempt > 0) {
            // N aus M: die Auswahl wechselt je Versuch (Rotation).
            $questions = $questions->shuffle()->take($quiz->questions_per_attempt);
        }

        if ($quiz->shuffle_questions) {
            $questions = $questions->shuffle();
        }

        return array_values($questions->map(function (LearningQuestion $question) use ($quiz): array {
            $options = $question->options;
            if ($quiz->shuffle_answers) {
                $options = $options->shuffle();
            }

            return [
                'id' => $question->id,
                'kind' => $question->kind->value,
                'prompt' => $question->prompt,
                'explanation' => $question->explanation,
                'points' => $question->points,
                'settings' => $question->settings,
                'options' => array_values($options->map(static fn ($option): array => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'is_correct' => $option->is_correct,
                    'position' => $option->position,
                    'match_key' => $option->match_key,
                ])->all()),
            ];
        })->values()->all());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<LearningQuizAttempt>
     */
    private function attemptsQuery(LearningEnrollment $enrollment, LearningQuiz $quiz) {
        return LearningQuizAttempt::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('learning_quiz_id', $quiz->id);
    }
}
