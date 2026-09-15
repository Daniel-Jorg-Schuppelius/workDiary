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

use App\Models\Learning\{LearningAnswer, LearningEnrollment, LearningQuestion, LearningQuiz, LearningQuizAttempt, LearningQuizAttemptWaiver};
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
    /** Nachfrist nach Ablauf, in der eine Formularabgabe noch zählt (der Countdown löst sie aus). */
    public const SUBMIT_GRACE_SECONDS = 30;

    public function __construct(
        private readonly LearningAnswerGrader $grader,
        private readonly LearningEnrollmentService $enrollments,
        private readonly LearningQuestionCatalogService $catalog,
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

        // Freischaltplan (MVP-788): eine gesperrte Prüfungseinheit startet nicht.
        $unit = $quiz->unit;
        if ($unit !== null) {
            app(LearningEnrollmentService::class)->guardReleased($enrollment, $unit, $now);
        }

        $previous = $this->attemptsQuery($enrollment, $quiz)->orderByDesc('attempt_no')->first();
        $count = $this->attemptsQuery($enrollment, $quiz)->count();

        // Versuchsfreigabe (MVP-785): genau ein weiterer Versuch trotz
        // Grenze oder Sperrfrist — verbraucht sich mit dem Start.
        $waiver = LearningQuizAttemptWaiver::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('learning_quiz_id', $quiz->id)
            ->whereNull('used_at')
            ->orderBy('id')
            ->first();

        if ($waiver === null && ! $quiz->allowsUnlimitedAttempts() && $count >= $quiz->max_attempts) {
            throw ValidationException::withMessages([
                'attempts' => (string) __('learning.errors.attempts_exhausted'),
            ]);
        }

        if ($waiver === null && $previous !== null && $quiz->retry_wait_hours > 0) {
            $ready = ($previous->submitted_at ?? $previous->started_at)?->copy()->addHours($quiz->retry_wait_hours);
            if ($ready !== null && $ready->greaterThan($now)) {
                throw ValidationException::withMessages([
                    'attempts' => (string) __('learning.errors.retry_wait', ['time' => $ready->translatedFormat('d.m.Y H:i')]),
                ]);
            }
        }

        return DB::transaction(function () use ($enrollment, $quiz, $context, $now, $count, $waiver): LearningQuizAttempt {
            $snapshot = $this->buildSnapshot($quiz);

            if ($snapshot === []) {
                throw ValidationException::withMessages([
                    'questions' => (string) __('learning.errors.quiz_without_questions'),
                ]);
            }

            $waiver?->update(['used_at' => $now]);

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

        // Nach Ablauf zählt nur, was rechtzeitig zwischengespeichert wurde —
        // eine kurze Nachfrist deckt die Abgabe, die der Countdown selbst
        // auslöst (MVP-783).
        $expired = $attempt->isExpired($now->copy()->subSeconds(self::SUBMIT_GRACE_SECONDS));
        $stored = $attempt->answers()->get()->keyBy('learning_question_id');
        $quiz = $attempt->quiz;

        // Antworten je Frage: Formular vor Zwischenspeicher — nach Ablauf
        // nur der Zwischenspeicher.
        $questions = $attempt->questions();
        $payloads = [];
        $missing = [];
        foreach ($questions as $index => $question) {
            $questionId = (int) ($question['id'] ?? 0);
            $formPayload = $answers[$questionId] ?? null;
            $formPayload = is_array($formPayload) && ! $expired ? $this->cleanPayload($formPayload) : null;
            $draft = $stored->get($questionId);
            $payload = $formPayload ?? ($draft->payload ?? null);
            $payloads[$questionId] = is_array($payload) ? $payload : null;

            if ($payloads[$questionId] === null && $quiz?->require_all_answered && ! $expired) {
                $missing[] = $index + 1;
            }
        }

        if ($missing !== []) {
            // Was schon beantwortet ist, bleibt als Entwurf erhalten — sonst
            // verlöre die Person beim Nachbessern ihre Eingaben.
            DB::transaction(function () use ($attempt, $payloads): void {
                foreach ($payloads as $questionId => $payload) {
                    if ($payload === null) {
                        continue;
                    }
                    LearningAnswer::query()->updateOrCreate(
                        ['learning_quiz_attempt_id' => $attempt->id, 'learning_question_id' => $questionId],
                        ['organization_id' => $attempt->organization_id, 'payload' => $payload, 'is_correct' => null, 'points_awarded' => 0],
                    );
                }
            });

            throw ValidationException::withMessages([
                'answers' => (string) __('learning.errors.answers_required', ['numbers' => implode(', ', $missing)]),
            ]);
        }

        return DB::transaction(function () use ($attempt, $questions, $payloads, $now, $quiz): LearningQuizAttempt {
            $score = 0;
            $needsManualGrading = false;

            foreach ($questions as $question) {
                $questionId = (int) ($question['id'] ?? 0);
                $payload = $payloads[$questionId] ?? null;

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

            $attempt->update([
                'submitted_at' => $now,
                'score_points' => $score,
                'score_percent' => $percent,
                // Solange ein Aufsatz auf Bewertung wartet, steht das
                // Gesamtergebnis noch nicht fest.
                'passed' => $needsManualGrading ? null : $this->passes($quiz, $score, $percent),
            ]);

            $this->completeUnitIfPassed($attempt->refresh());

            return $attempt;
        });
    }

    /**
     * Antwort zwischenspeichern (MVP-783): der Versuch bleibt offen, bewertet
     * wird nichts — `is_correct` bleibt null. Nach Ablauf wird nichts mehr
     * angenommen, sonst ließe sich das Zeitlimit umgehen.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function saveAnswer(LearningQuizAttempt $attempt, int $questionId, ?array $payload, ?bool $flagged = null, ?Carbon $now = null): LearningAnswer {
        $now ??= Carbon::now();

        if (! $attempt->isOpen() || $attempt->isExpired($now)) {
            throw ValidationException::withMessages([
                'attempt' => (string) __('learning.errors.attempt_closed'),
            ]);
        }

        $known = array_map(static fn (array $q): int => (int) ($q['id'] ?? 0), $attempt->questions());
        if (! in_array($questionId, $known, true)) {
            throw ValidationException::withMessages([
                'question' => (string) __('learning.errors.question_foreign'),
            ]);
        }

        $values = [
            'organization_id' => $attempt->organization_id,
            'payload' => $payload !== null ? $this->cleanPayload($payload) : null,
            'is_correct' => null,
            'points_awarded' => 0,
        ];
        if ($flagged !== null) {
            $values['flagged'] = $flagged;
        }

        return LearningAnswer::query()->updateOrCreate(
            ['learning_quiz_attempt_id' => $attempt->id, 'learning_question_id' => $questionId],
            $values,
        );
    }

    /**
     * Bestanden (MVP-793): Prozentgrenze UND — falls gesetzt — Punktgrenze.
     * Beides zusammen, damit eine kleine Prüfung nicht mit einem
     * einzigen Treffer „bestanden" ist.
     */
    private function passes(?LearningQuiz $quiz, int $score, int $percent): bool {
        $passPercent = $quiz->pass_percent ?? 80;
        $passPoints = $quiz->pass_points ?? null;

        return $percent >= $passPercent && ($passPoints === null || $score >= $passPoints);
    }

    /**
     * Leere Eingaben zählen nicht als Antwort: ein Formular schickt auch
     * unbeantwortete Fragen mit leeren Feldern.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function cleanPayload(array $payload): ?array {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = array_values(array_filter($value, static fn ($v): bool => $v !== null && $v !== ''));
                if ($value === []) {
                    continue;
                }
            } elseif ($value === null || trim((string) $value) === '') {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean === [] ? null : $clean;
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
            'passed' => $open ? null : $this->passes($attempt->quiz, $score, $percent),
        ]);

        $this->completeUnitIfPassed($attempt->refresh());
    }

    /**
     * Versuchsfreigabe erteilen (MVP-785): Begründung und Person sind Pflicht,
     * die Prüfung muss zum Kurs der Einschreibung gehören.
     */
    public function grantWaiver(LearningEnrollment $enrollment, LearningQuiz $quiz, ?User $actor, string $reason): LearningQuizAttemptWaiver {
        if ($quiz->unit?->learning_course_id !== $enrollment->learning_course_id) {
            throw ValidationException::withMessages([
                'quiz' => (string) __('learning.errors.quiz_foreign'),
            ]);
        }

        return LearningQuizAttemptWaiver::query()->create([
            'organization_id' => $enrollment->organization_id,
            'learning_enrollment_id' => $enrollment->id,
            'learning_quiz_id' => $quiz->id,
            'granted_by_user_id' => $actor?->id,
            'reason' => trim($reason),
        ]);
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

        // Ziehregeln (MVP-782): je Kategorie N zufällige Katalogfragen —
        // zusätzlich zur festen Liste, ohne Doppelung.
        $drawn = $this->catalog->drawFor($quiz, array_values(array_map('intval', $questions->pluck('id')->all())));
        if ($drawn !== []) {
            $questions = $questions->concat($drawn)->values();
        }

        $subset = $quiz->questions_per_attempt;
        // Prozent-Teilmenge (MVP-793): Anteil der verfügbaren Fragen, mindestens eine.
        if (($subset === null || $subset <= 0) && $quiz->questions_per_attempt_percent !== null && $quiz->questions_per_attempt_percent > 0) {
            $subset = max(1, (int) ceil($questions->count() * $quiz->questions_per_attempt_percent / 100));
        }
        if ($subset !== null && $subset > 0) {
            // N aus M: die Auswahl wechselt je Versuch (Rotation).
            $questions = $questions->shuffle()->take($subset);
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
                    'points' => $option->points,
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
