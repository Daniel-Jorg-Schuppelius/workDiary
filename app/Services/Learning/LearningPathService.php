<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningPathService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\{LearningCourseStatus, LearningEnrollmentSource};
use App\Models\Learning\{LearningEnrollment, LearningPath, LearningPathItem};
use App\Models\{Organization, User};
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;

/**
 * Lernpfade zuweisen (Feature 149, MVP-745).
 *
 * Ein Lernpfad ist eine **Reihenfolge von Kursen mit Fristen** — die
 * Einarbeitung einer neuen Person, nicht ein zweiter Pflichtkatalog. Das
 * Soll bleibt bei Feature 145; der Pfad liefert nur die Abfolge.
 *
 * **Zugewiesen wird über die reguläre Einschreibung.** Ein eigener
 * Zuweisungsweg wäre eine zweite Wahrheit über den Lernstand — und
 * doppelte Zuweisung ist bewusst kein Fehler, sie bringt die laufende
 * Einschreibung zurück.
 */
class LearningPathService {
    public function __construct(
        private readonly LearningEnrollmentService $enrollments,
    ) {}

    /**
     * Pfad einer Person zuweisen.
     *
     * Die Frist je Station rechnet ab **heute**, nicht ab dem Beginn der
     * vorherigen — sonst verschöbe sich der ganze Pfad, sobald jemand eine
     * Station liegen lässt.
     *
     * @return list<LearningEnrollment>
     */
    public function assign(LearningPath $path, User $user, ?Carbon $from = null): array {
        $from ??= Carbon::today();

        return DB::transaction(function () use ($path, $user, $from): array {
            $created = [];

            foreach ($this->itemsOf($path) as $item) {
                $course = $item->course;

                // Ein Entwurf ist kein zuweisbarer Stoff — die Station wird
                // übersprungen, statt den ganzen Pfad scheitern zu lassen.
                if ($course === null || $course->status !== LearningCourseStatus::Released) {
                    continue;
                }

                $created[] = $this->enrollments->enroll($course, $user, [
                    'source' => LearningEnrollmentSource::Path->value,
                    'due_at' => $item->due_days !== null
                        ? $from->copy()->addDays($item->due_days)
                        : ($path->duration_days !== null ? $from->copy()->addDays($path->duration_days) : null),
                ]);
            }

            return $created;
        });
    }

    /**
     * Automatische Zuweisung: alle aktiven Pfade mit Zielrolle an die
     * Personen dieser Rolle.
     *
     * Läuft wiederholt — deshalb ist die doppelte Zuweisung kein Fehler,
     * sondern ein No-Op.
     *
     * @return array{paths: int, enrollments: int}
     */
    public function assignByRole(Organization $organization, ?Carbon $from = null): array {
        $paths = LearningPath::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereNotNull('target_role')
            ->get();

        $enrollments = 0;

        foreach ($paths as $path) {
            foreach ($this->usersWithRole($organization, (string) $path->target_role) as $user) {
                $enrollments += count($this->assign($path, $user, $from));
            }
        }

        return ['paths' => $paths->count(), 'enrollments' => $enrollments];
    }

    /**
     * Fortschritt einer Person auf einem Pfad — Stationen mit Zustand.
     *
     * @return list<array{item: LearningPathItem, enrollment: LearningEnrollment|null, done: bool}>
     */
    public function progressFor(LearningPath $path, User $user): array {
        $enrollments = LearningEnrollment::query()
            ->where('user_id', $user->id)
            ->whereIn('learning_course_id', $this->itemsOf($path)->pluck('learning_course_id'))
            ->get()
            ->keyBy('learning_course_id');

        $rows = [];

        foreach ($this->itemsOf($path) as $item) {
            $enrollment = $enrollments->get($item->learning_course_id);

            $rows[] = [
                'item' => $item,
                'enrollment' => $enrollment,
                'done' => $enrollment?->status->isFinal() ?? false,
            ];
        }

        return $rows;
    }

    /** @return Collection<int, LearningPathItem> */
    private function itemsOf(LearningPath $path): Collection {
        return $path->items()->with('course')->orderBy('position')->get();
    }

    /** @return Collection<int, User> */
    private function usersWithRole(Organization $organization, string $role): Collection {
        return User::query()
            ->where('organization_id', $organization->id)
            ->whereNull('deactivated_at')
            ->whereHas('roles', fn ($q) => $q->where('name', $role))
            ->get();
    }
}
