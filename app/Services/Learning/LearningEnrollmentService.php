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
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningUnit, LearningUnitProgress};
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

        $version = $course->currentVersion();

        return DB::transaction(function () use ($course, $learner, $attributes, $version): LearningEnrollment {
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

            return $enrollment;
        });
    }

    /** Erste Interaktion: setzt die Einschreibung auf „in Bearbeitung". */
    public function start(LearningEnrollment $enrollment): LearningEnrollment {
        $this->guardOpen($enrollment);

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
