<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningEnrollmentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\{LearningCourseStatus, LearningEnrollmentSource, LearningEnrollmentStatus, LearningProgressStatus};
use App\Models\{ExternalParticipant, User};
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningTimeSession, LearningUnit, LearningUnitProgress};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Einschreibung und Fortschritt (Feature 149, MVP-737) — einzige
 * Schreibstelle.
 *
 * Zwei Regeln stecken hier und nirgendwo sonst:
 *  1. Eingeschrieben wird nur in einen FREIGEGEBENEN Kurs, und die
 *     Einschreibung merkt sich die Version. Ein Entwurf hat keinen
 *     verlässlichen Stoffstand.
 *  2. Ein Kurs gilt erst als abgeschlossen, wenn ALLE Pflichteinheiten
 *     abgeschlossen sind — der Abschluss wird nie geraten.
 */
class LearningEnrollmentService {
    public function __construct(
        private readonly LearningCompletionService $completion,
        private readonly LearningNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function enroll(
        LearningCourse $course,
        User|ExternalParticipant $learner,
        array $attributes = [],
    ): LearningEnrollment {
        if ($course->status !== LearningCourseStatus::Released) {
            throw ValidationException::withMessages([
                'course' => (string) __('learning.errors.enroll_requires_release'),
            ]);
        }

        $source = LearningEnrollmentSource::tryFrom((string) ($attributes['source'] ?? LearningEnrollmentSource::Manual->value)) ?? LearningEnrollmentSource::Manual;

        // Verfügbarkeitsfenster (MVP-788) gilt für die Selbsteinschreibung;
        // eine Zuweisung durch die Verwaltung bleibt frei.
        if ($source === LearningEnrollmentSource::Self && ! $course->isAvailableOn()) {
            throw ValidationException::withMessages([
                'course' => (string) __('learning.errors.course_not_available'),
            ]);
        }

        $version = $course->currentVersion();

        $created = false;
        $enrollment = DB::transaction(function () use ($course, $learner, $attributes, $version, &$created): LearningEnrollment {
            $isUser = $learner instanceof User;

            $existing = LearningEnrollment::query()
                ->where('learning_course_id', $course->id)
                ->when($isUser, fn ($q) => $q->where('user_id', $learner->id))
                ->when(! $isUser, fn ($q) => $q->where('external_participant_id', $learner->id))
                ->first();

            // Doppelte Zuweisung ist kein Fehler — sie bringt nur eine
            // bereits laufende Einschreibung zurück (Pflichtmatrix läuft
            // wiederholt).
            if ($existing !== null) {
                return $existing;
            }

            // Teilnehmergrenze (MVP-788): aktive Einschreibungen zählen; die
            // Pflichtmatrix umgeht sie — ein Soll wartet nicht auf einen Platz.
            $source = LearningEnrollmentSource::tryFrom((string) ($attributes['source'] ?? LearningEnrollmentSource::Manual->value));
            if ($source !== LearningEnrollmentSource::Requirement && ! $course->hasCapacity()) {
                throw ValidationException::withMessages([
                    'course' => (string) __('learning.errors.course_full', ['max' => (int) $course->max_enrollments]),
                ]);
            }

            $enrollment = LearningEnrollment::query()->create([
                'organization_id' => $course->organization_id,
                'learning_course_id' => $course->id,
                'learning_course_version_id' => $version?->id,
                'user_id' => $isUser ? $learner->id : null,
                'external_participant_id' => $isUser ? null : $learner->id,
                'status' => LearningEnrollmentStatus::Assigned->value,
                'source' => $attributes['source'] ?? LearningEnrollmentSource::Manual->value,
                'assigned_by_user_id' => $attributes['assigned_by_user_id'] ?? null,
                'due_at' => $attributes['due_at'] ?? null,
                'access_until' => $this->resolveAccessUntil($course, $attributes),
            ]);

            $this->recordEvent($enrollment, null, LearningEnrollmentStatus::Assigned, $attributes['reason'] ?? null);
            $created = true;

            return $enrollment;
        });

        // Pflicht-Einschreibungen meldet bereits das Trainingsmanagement (145)
        // — sonst käme dieselbe Person zweimal Post (MVP-780).
        if ($created && $enrollment->source !== LearningEnrollmentSource::Requirement) {
            $this->notifier->enrolled($enrollment);
        }

        return $enrollment;
    }

    /** Erste Interaktion: setzt die Einschreibung auf „in Bearbeitung". */
    public function start(LearningEnrollment $enrollment): LearningEnrollment {
        $this->guardOpen($enrollment);
        $this->guardPrerequisites($enrollment);

        if ($enrollment->status === LearningEnrollmentStatus::Assigned) {
            $from = $enrollment->status;
            $enrollment->update([
                'status' => LearningEnrollmentStatus::InProgress->value,
                'started_at' => $enrollment->started_at ?? now(),
            ]);
            $this->recordEvent($enrollment, $from, LearningEnrollmentStatus::InProgress);
        }

        return $enrollment->refresh();
    }

    /** Einheit abschließen; schließt den Kurs, sobald die Pflicht erfüllt ist. */
    public function completeUnit(LearningEnrollment $enrollment, LearningUnit $unit, int $progressPercent = 100): LearningUnitProgress {
        $this->guardOpen($enrollment);

        if ($unit->learning_course_id !== $enrollment->learning_course_id) {
            throw ValidationException::withMessages([
                'unit' => (string) __('learning.errors.unit_foreign'),
            ]);
        }

        // Freischaltplan (MVP-788): die Sperre sitzt HIER, nicht in der
        // Ansicht — Player, Externe, Portal und Offline-Sync laufen alle
        // durch diese Methode.
        $this->guardReleased($enrollment, $unit);

        // Mindestverweildauer: gezählt wird ab dem ersten Öffnen der Einheit
        // (markSeen) oder über die Lernzeit-Sitzungen der Einheit.
        $minSeconds = $unit->minSeconds();
        if ($minSeconds > 0) {
            $spent = $this->secondsSpentOn($enrollment, $unit);
            if ($spent < $minSeconds) {
                throw ValidationException::withMessages([
                    'unit' => (string) __('learning.errors.min_seconds_not_reached', [
                        'minutes' => (int) ceil(($minSeconds - $spent) / 60),
                    ]),
                ]);
            }
        }

        return DB::transaction(function () use ($enrollment, $unit, $progressPercent): LearningUnitProgress {
            $this->start($enrollment);

            $progress = LearningUnitProgress::query()->firstOrNew([
                'learning_enrollment_id' => $enrollment->id,
                'learning_unit_id' => $unit->id,
            ]);

            $progress->fill([
                'organization_id' => $enrollment->organization_id,
                'status' => LearningProgressStatus::Completed->value,
                'started_at' => $progress->started_at ?? now(),
                'completed_at' => now(),
                'attempts' => (int) $progress->attempts + 1,
                'progress_percent' => max(0, min(100, $progressPercent)),
            ])->save();

            $this->completeIfDone($enrollment);

            return $progress->refresh();
        });
    }

    /** Freischaltplan durchsetzen — auch für Prüfungsstart und Abgabe. */
    public function guardReleased(LearningEnrollment $enrollment, LearningUnit $unit, ?Carbon $now = null): void {
        if ($unit->isReleasedFor($enrollment, $now)) {
            return;
        }

        $date = $unit->releaseDateFor($enrollment);

        throw ValidationException::withMessages([
            'unit' => $date !== null && $date->gt(($now ?? Carbon::now())->copy()->startOfDay())
                ? (string) __('learning.errors.unit_not_released', ['date' => $date->translatedFormat('d.m.Y')])
                : (string) __('learning.errors.unit_locked_by_sequence'),
        ]);
    }

    /**
     * Erstes Öffnen merken (MVP-788): nur für Einheiten mit
     * Mindestverweildauer, sonst entstünde für jeden Seitenaufruf ein
     * Fortschrittssatz. Abgeschlossene Einheiten bleiben unberührt.
     *
     * @param  iterable<LearningUnit>  $units
     */
    public function markSeen(LearningEnrollment $enrollment, iterable $units, ?Carbon $now = null): void {
        if ($enrollment->status->isFinal()) {
            return;
        }

        foreach ($units as $unit) {
            if ($unit->minSeconds() <= 0 || ! $unit->isReleasedFor($enrollment, $now)) {
                continue;
            }

            LearningUnitProgress::query()->firstOrCreate([
                'learning_enrollment_id' => $enrollment->id,
                'learning_unit_id' => $unit->id,
            ], [
                'organization_id' => $enrollment->organization_id,
                'status' => LearningProgressStatus::Started->value,
                'started_at' => $now ?? now(),
                'attempts' => 0,
                'progress_percent' => 0,
            ]);
        }
    }

    /** Verweildauer auf einer Einheit: Fortschrittsbeginn oder Lernzeit, das Größere. */
    public function secondsSpentOn(LearningEnrollment $enrollment, LearningUnit $unit, ?Carbon $now = null): int {
        $now ??= Carbon::now();

        $startedAt = LearningUnitProgress::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('learning_unit_id', $unit->id)
            ->value('started_at');
        $sinceOpen = $startedAt !== null ? max(0, Carbon::parse((string) $startedAt)->diffInSeconds($now, false)) : 0;

        $sessions = LearningTimeSession::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('learning_unit_id', $unit->id)
            ->get(['started_at', 'ended_at', 'active_seconds']);
        $tracked = 0;
        foreach ($sessions as $session) {
            $tracked += $session->ended_at === null && $session->started_at !== null
                ? max(0, (int) $session->started_at->diffInSeconds($now, false))
                : (int) $session->active_seconds;
        }

        return (int) max($sinceOpen, $tracked);
    }

    /**
     * Schließt den Kurs, wenn alle Pflichteinheiten erledigt sind. Der
     * Rückfluss in Soll (145), Unterweisungsnachweis (132) und
     * Qualifikation (013) folgt mit MVP-740 an genau dieser Stelle.
     */
    public function completeIfDone(LearningEnrollment $enrollment): bool {
        $mandatoryIds = LearningUnit::query()
            ->where('learning_course_id', $enrollment->learning_course_id)
            ->where('is_mandatory', true)
            ->pluck('id');

        if ($mandatoryIds->isEmpty()) {
            return false;
        }

        $doneIds = LearningUnitProgress::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('status', LearningProgressStatus::Completed->value)
            ->pluck('learning_unit_id');

        if ($mandatoryIds->diff($doneIds)->isNotEmpty()) {
            return false;
        }

        $from = $enrollment->status;
        $enrollment->update([
            'status' => LearningEnrollmentStatus::Completed->value,
            'completed_at' => now(),
            'points_earned' => (int) LearningUnit::query()
                ->where('learning_course_id', $enrollment->learning_course_id)
                ->sum('points'),
        ]);
        $this->recordEvent($enrollment, $from, LearningEnrollmentStatus::Completed);

        // Einzige Stelle, an der ein Abschluss nach außen wirkt: Zertifikat,
        // Unterweisungsnachweis (132), Soll-Erfüllung (145) und
        // Qualifikation (013) — siehe LearningCompletionService.
        $this->completion->apply($enrollment->refresh());

        return true;
    }

    /**
     * Frist und Zugang nachträglich ändern (MVP-778). Die Begründung ist
     * Pflicht und landet als Ereignis an der Einschreibung — sonst wäre eine
     * verlängerte Pflichtfrist später nicht mehr erklärbar.
     */
    public function extendAccess(
        LearningEnrollment $enrollment,
        ?string $dueAt,
        ?string $accessUntil,
        ?User $actor,
        string $reason,
    ): LearningEnrollment {
        if ($enrollment->status->isFinal()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.enrollment_closed'),
            ]);
        }

        if ($dueAt !== null && $accessUntil !== null && $accessUntil < $dueAt) {
            throw ValidationException::withMessages([
                'access_until' => (string) __('learning.errors.access_before_due'),
            ]);
        }

        return DB::transaction(function () use ($enrollment, $dueAt, $accessUntil, $actor, $reason): LearningEnrollment {
            $enrollment->update([
                'due_at' => $dueAt,
                'access_until' => $accessUntil,
            ]);
            $this->recordEvent($enrollment, $enrollment->status, $enrollment->status, $reason, $actor);

            return $enrollment->refresh();
        });
    }

    public function cancel(LearningEnrollment $enrollment, ?User $actor = null, ?string $reason = null): LearningEnrollment {
        if ($enrollment->source === LearningEnrollmentSource::Requirement) {
            // Pflicht-Einschreibungen zu stornieren würde das Soll aus
            // Feature 145 still unerfüllt zurücklassen.
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.cancel_mandatory'),
            ]);
        }

        $from = $enrollment->status;
        $enrollment->update(['status' => LearningEnrollmentStatus::Cancelled->value]);
        $this->recordEvent($enrollment, $from, LearningEnrollmentStatus::Cancelled, $reason, $actor);

        return $enrollment->refresh();
    }

    /**
     * Voraussetzungen, die der lernenden Person noch fehlen (MVP-784).
     * Pflicht-Einschreibungen kennen keine Voraussetzungen — ein
     * gesetzliches Soll scheitert nicht an einer Kursoption.
     *
     * @return list<LearningCourse>
     */
    public function missingPrerequisites(LearningEnrollment $enrollment): array {
        if ($enrollment->source === LearningEnrollmentSource::Requirement) {
            return [];
        }

        $course = $enrollment->course;
        if ($course === null) {
            return [];
        }
        /** @var list<LearningCourse> $required */
        $required = $course->prerequisites()->get()->all();
        if ($required === []) {
            return [];
        }

        $completed = LearningEnrollment::query()
            ->whereIn('learning_course_id', array_map(static fn (LearningCourse $c): int => $c->id, $required))
            ->where('status', LearningEnrollmentStatus::Completed->value)
            ->when($enrollment->user_id !== null, fn ($q) => $q->where('user_id', $enrollment->user_id))
            ->when($enrollment->user_id === null, fn ($q) => $q->where('external_participant_id', $enrollment->external_participant_id))
            ->pluck('learning_course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $missing = array_values(array_filter($required, static fn (LearningCourse $c): bool => ! in_array($c->id, $completed, true)));

        if ($course->prerequisite_mode === 'any' && count($missing) < count($required)) {
            return [];
        }

        return $missing;
    }

    private function guardPrerequisites(LearningEnrollment $enrollment): void {
        if ($enrollment->status !== LearningEnrollmentStatus::Assigned) {
            return;
        }

        $missing = $this->missingPrerequisites($enrollment);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'prerequisites' => (string) __('learning.errors.prerequisites_missing', [
                    'courses' => implode(', ', array_map(static fn (LearningCourse $c): string => $c->title, $missing)),
                ]),
            ]);
        }
    }

    /**
     * Anrechnung (MVP-784): eine bestandene Prüfung ohne Kurs schließt den
     * Zielkurs ab — über die reguläre Einschreibung, damit Zertifikat,
     * Nachweis und Qualifikation an genau derselben Stelle entstehen.
     */
    public function creditFromExam(LearningEnrollment $examEnrollment, LearningCourse $target): ?LearningEnrollment {
        $learner = $examEnrollment->user ?? $examEnrollment->externalParticipant;
        if ($learner === null || $target->status !== LearningCourseStatus::Released) {
            return null;
        }

        $enrollment = $this->enroll($target, $learner, [
            'source' => LearningEnrollmentSource::Exam->value,
            'reason' => $examEnrollment->course?->title,
        ]);

        if ($enrollment->status->isFinal()) {
            return $enrollment;
        }

        // Ergebnis der Prüfung: am besten bestandenen Versuch ablesen — die
        // Einschreibung selbst trägt keinen Prozentwert.
        $score = $examEnrollment->score_percent
            ?? \App\Models\Learning\LearningQuizAttempt::query()
                ->where('learning_enrollment_id', $examEnrollment->id)
                ->where('passed', true)
                ->orderByDesc('score_percent')
                ->value('score_percent');

        $from = $enrollment->status;
        $enrollment->update([
            'status' => LearningEnrollmentStatus::Completed->value,
            'started_at' => $enrollment->started_at ?? now(),
            'completed_at' => now(),
            'score_percent' => $score !== null ? (int) $score : null,
            'points_earned' => (int) LearningUnit::query()->where('learning_course_id', $target->id)->sum('points'),
        ]);
        $this->recordEvent($enrollment, $from, LearningEnrollmentStatus::Completed, $examEnrollment->course?->title);
        $this->completion->apply($enrollment->refresh());

        return $enrollment->refresh();
    }

    private function guardOpen(LearningEnrollment $enrollment): void {
        if ($enrollment->status->isFinal()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.enrollment_closed'),
            ]);
        }

        if ($enrollment->isAccessExpired()) {
            throw ValidationException::withMessages([
                'status' => (string) __('learning.errors.access_expired'),
            ]);
        }
    }

    private function recordEvent(
        LearningEnrollment $enrollment,
        ?LearningEnrollmentStatus $from,
        LearningEnrollmentStatus $to,
        ?string $reason = null,
        ?User $actor = null,
    ): void {
        $enrollment->events()->create([
            'organization_id' => $enrollment->organization_id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->id,
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveAccessUntil(LearningCourse $course, array $attributes): ?string {
        if (array_key_exists('access_until', $attributes)) {
            return $attributes['access_until'];
        }

        return $course->access_days !== null
            ? now()->addDays($course->access_days)->toDateString()
            : null;
    }
}
