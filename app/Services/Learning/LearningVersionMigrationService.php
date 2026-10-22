<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningVersionMigrationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningEnrollmentStatus;
use App\Models\Learning\{LearningCourse, LearningCourseVersion, LearningEnrollment};
use App\Models\Platform\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * „Teilnehmer auf Version N heben“ (Feature 149 §4, MVP-1272). Laufende
 * Einschreibungen bleiben auf ihrer Version, bis jemand sie ausdrücklich
 * hebt — mit Grund im Verlauf der Einschreibung. Der Fortschritt hängt an
 * den Einheiten und bleibt für jede Einheit erhalten, die beide Versionen
 * kennen; neue Pflichteinheiten sind danach offen.
 */
class LearningVersionMigrationService {
    public function __construct(private readonly LearningEnrollmentService $enrollments) {}

    /**
     * Unterschied zweier Versionen nach Einheiten.
     *
     * @return array{kept: list<string>, changed: list<string>, added: list<string>, removed: list<string>}
     */
    public function diff(?LearningCourseVersion $from, LearningCourseVersion $to): array {
        $before = $this->unitsOf($from);
        $after = $this->unitsOf($to);
        $diff = ['kept' => [], 'changed' => [], 'added' => [], 'removed' => []];
        foreach ($after as $id => $unit) {
            if (! isset($before[$id])) {
                $diff['added'][] = $unit['title'];
            } elseif ($before[$id]['content'] !== $unit['content']) {
                $diff['changed'][] = $unit['title'];
            } else {
                $diff['kept'][] = $unit['title'];
            }
        }
        foreach (array_diff_key($before, $after) as $unit) {
            $diff['removed'][] = $unit['title'];
        }

        return $diff;
    }

    /**
     * Offene Einschreibungen des Kurses, die nicht auf der aktuellen Version stehen.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, LearningEnrollment>
     */
    public function behind(LearningCourse $course) {
        $current = $course->currentVersion();

        return LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->whereIn('status', [LearningEnrollmentStatus::Assigned->value, LearningEnrollmentStatus::InProgress->value])
            ->when($current !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('learning_course_version_id')->orWhere('learning_course_version_id', '!=', $current?->id)))
            ->with(['courseVersion', 'user', 'externalParticipant'])
            ->get();
    }

    public function moveToVersion(LearningEnrollment $enrollment, LearningCourseVersion $version, ?User $actor, string $reason): LearningEnrollment {
        if ($enrollment->status->isFinal()) {
            throw ValidationException::withMessages(['status' => (string) __('learning.errors.enrollment_closed')]);
        }
        if ((int) $version->learning_course_id !== (int) $enrollment->learning_course_id) {
            throw ValidationException::withMessages(['version' => (string) __('learning.errors.version_foreign')]);
        }
        if ((int) $enrollment->learning_course_version_id === (int) $version->id) {
            return $enrollment;
        }

        return DB::transaction(function () use ($enrollment, $version, $actor, $reason): LearningEnrollment {
            $from = $enrollment->courseVersion;
            $diff = $this->diff($from, $version);
            $enrollment->update(['learning_course_version_id' => $version->id]);
            $enrollment->record('version_changed', [
                'from_version' => $from?->version,
                'to_version' => $version->version,
                'kept' => count($diff['kept']),
                'changed' => count($diff['changed']),
                'added' => count($diff['added']),
                'removed' => count($diff['removed']),
            ], $actor, extra: [
                'from_status' => $enrollment->status,
                'to_status' => $enrollment->status,
                'reason' => trim($reason),
            ]);
            // Fallen Pflichteinheiten weg, kann der Kurs damit erledigt sein.
            $this->enrollments->completeIfDone($enrollment);

            return $enrollment->refresh();
        });
    }

    /** Alle offenen Einschreibungen auf die aktuelle Version; Rückgabe = Anzahl. */
    public function moveAllToCurrent(LearningCourse $course, ?User $actor, string $reason): int {
        $current = $course->currentVersion();
        if ($current === null) {
            throw ValidationException::withMessages(['version' => (string) __('learning.errors.no_released_version')]);
        }
        $moved = 0;
        foreach ($this->behind($course) as $enrollment) {
            $this->moveToVersion($enrollment, $current, $actor, $reason);
            $moved++;
        }

        return $moved;
    }

    /** @return array<int, array{title: string, content: list<mixed>}> */
    private function unitsOf(?LearningCourseVersion $version): array {
        $units = [];
        foreach ((array) ($version?->snapshot()['units'] ?? []) as $unit) {
            if (! is_array($unit) || ! isset($unit['id'])) {
                continue;
            }
            $units[(int) $unit['id']] = [
                'title' => (string) ($unit['title'] ?? ''),
                'content' => [$unit['kind'] ?? null, $unit['blocks'] ?? null, $unit['completion_rule'] ?? null, $unit['is_mandatory'] ?? null],
            ];
        }

        return $units;
    }
}
