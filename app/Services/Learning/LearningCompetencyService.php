<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCompetencyService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{Competency, CompetencyRequirement, LearningEnrollment, UserCompetency};
use App\Models\{Organization, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kompetenzen und Lückenanalyse (Feature 149, MVP-745) — einzige
 * Schreibstelle für erreichte Stufen.
 *
 * Die Regeln:
 *  1. **Eine erreichte Stufe wird nie gesenkt**, wenn derselbe Kurs erneut
 *     abgeschlossen wird — Wiederholung ist keine Rückstufung. Eine
 *     ausdrückliche Einschätzung durch einen Menschen darf senken.
 *  2. Die Kompetenz **sperrt nichts**. Sie zeigt Lücken; die Sperrwirkung
 *     bleibt bei der Qualifikation (Feature 013).
 *  3. Abgelaufene Stufen zählen in der Lückenanalyse **nicht** als erfüllt.
 */
class LearningCompetencyService {
    /** Kompetenz aus einem Kursabschluss belegen. */
    public function grantFromCourse(LearningEnrollment $enrollment, ?Carbon $now = null): ?UserCompetency {
        $now ??= Carbon::now();
        $course = $enrollment->course;
        $user = $enrollment->user;

        if ($course?->competency_id === null || $user === null) {
            return null;
        }

        $competency = $course->competency;

        if ($competency === null) {
            return null;
        }

        $level = $competency->clampLevel((int) ($course->competency_level ?? 1));

        return $this->apply(
            $user,
            $competency,
            $level,
            'course',
            $now,
            enrollment: $enrollment,
            validUntil: $course->validity_months !== null
                ? $now->copy()->addMonths($course->validity_months)->toDateString()
                : null,
        );
    }

    /** Einschätzung durch eine Führungskraft — darf auch senken. */
    public function assess(User $user, Competency $competency, int $level, User $assessor, ?string $note = null, ?Carbon $now = null): UserCompetency {
        return $this->apply(
            $user,
            $competency,
            $competency->clampLevel($level),
            'assessment',
            $now ?? Carbon::now(),
            assessor: $assessor,
            note: $note,
            allowDowngrade: true,
        );
    }

    /**
     * Lückenanalyse für eine Person: welche Soll-Kompetenzen fehlen oder
     * sind zu niedrig.
     *
     * @param  list<string>  $roles  Rollen-Slugs der Person
     * @return list<array{competency: Competency, required: int, actual: int, gap: int}>
     */
    public function gapsFor(User $user, array $roles, ?int $teamId = null, ?Carbon $on = null): array {
        $on ??= Carbon::today();

        $subjects = array_map(static fn (string $role): array => ['role', $role], $roles);
        if ($teamId !== null) {
            $subjects[] = ['team', (string) $teamId];
        }

        if ($subjects === []) {
            return [];
        }

        $requirements = CompetencyRequirement::query()
            ->with('competency')
            ->where('is_active', true)
            ->where(function ($query) use ($subjects): void {
                foreach ($subjects as [$kind, $key]) {
                    $query->orWhere(function ($q) use ($kind, $key): void {
                        $q->where('subject_kind', $kind)->where('subject_key', $key);
                    });
                }
            })
            ->get();

        $actual = UserCompetency::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('competency_id');

        $gaps = [];
        foreach ($requirements as $requirement) {
            $competency = $requirement->competency;

            if ($competency === null || ! $competency->is_active) {
                continue;
            }

            $have = $actual->get($requirement->competency_id);
            // Abgelaufen zählt nicht als erfüllt.
            $level = ($have !== null && ! $have->isExpired($on)) ? (int) $have->level : 0;
            $gap = (int) $requirement->required_level - $level;

            if ($gap > 0) {
                $gaps[] = [
                    'competency' => $competency,
                    'required' => (int) $requirement->required_level,
                    'actual' => $level,
                    'gap' => $gap,
                ];
            }
        }

        return $gaps;
    }

    /**
     * Matrix für ein Team: Personen × Kompetenzen mit erreichter Stufe.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return array{competencies: list<Competency>, rows: list<array{user: User, levels: array<int, int>}>}
     */
    public function matrixFor(Organization $organization, $users): array {
        $competencies = Competency::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $levels = UserCompetency::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $rows = [];
        foreach ($users as $user) {
            $own = [];
            foreach ($levels->get($user->id, collect()) as $entry) {
                $own[(int) $entry->competency_id] = (int) $entry->level;
            }
            $rows[] = ['user' => $user, 'levels' => $own];
        }

        return ['competencies' => array_values($competencies->all()), 'rows' => $rows];
    }

    private function apply(
        User $user,
        Competency $competency,
        int $level,
        string $source,
        Carbon $now,
        ?LearningEnrollment $enrollment = null,
        ?User $assessor = null,
        ?string $validUntil = null,
        ?string $note = null,
        bool $allowDowngrade = false,
    ): UserCompetency {
        return DB::transaction(function () use ($user, $competency, $level, $source, $now, $enrollment, $assessor, $validUntil, $note, $allowDowngrade): UserCompetency {
            $existing = UserCompetency::query()
                ->where('user_id', $user->id)
                ->where('competency_id', $competency->id)
                ->first();

            if ($existing === null) {
                return UserCompetency::query()->create([
                    'organization_id' => $competency->organization_id,
                    'user_id' => $user->id,
                    'competency_id' => $competency->id,
                    'level' => $level,
                    'source' => $source,
                    'learning_enrollment_id' => $enrollment?->id,
                    'assessed_by_user_id' => $assessor?->id,
                    'assessed_on' => $now->toDateString(),
                    'valid_until' => $validUntil,
                    'note' => $note,
                ]);
            }

            // Wiederholung ist keine Rückstufung.
            $newLevel = $allowDowngrade ? $level : max($level, (int) $existing->level);

            $existing->update([
                'level' => $newLevel,
                'source' => $source,
                'learning_enrollment_id' => $enrollment->id ?? $existing->learning_enrollment_id,
                'assessed_by_user_id' => $assessor->id ?? $existing->assessed_by_user_id,
                'assessed_on' => $now->toDateString(),
                'valid_until' => $validUntil ?? $existing->valid_until?->toDateString(),
                'note' => $note ?? $existing->note,
            ]);

            return $existing->refresh();
        });
    }
}
