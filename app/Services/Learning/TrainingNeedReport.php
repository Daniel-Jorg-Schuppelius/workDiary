<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingNeedReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningCourseStatus;
use App\Models\Learning\LearningCourse;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Reporting\Contracts\TrainingNeedSource;

/**
 * Schulungsbedarf aus den Kompetenzlücken aller aktiven Personen (MVP-926),
 * gerechnet über `LearningCompetencyService::gapsFor()` (Soll je Rolle,
 * abgelaufene Nachweise zählen nicht), mit Kursen, die die Kompetenz verleihen.
 */
final class TrainingNeedReport implements TrainingNeedSource {
    public function __construct(
        private readonly LearningCompetencyService $competencies,
        private readonly ModuleStatusResolver $modules,
    ) {}

    public function trainingNeeds(Organization $organization): array {
        if (! $this->modules->isActiveFor($organization, 'module.lms')) {
            return [];
        }
        $byCompetency = [];
        $users = User::query()->where('organization_id', $organization->id)->whereNull('deactivated_at')->with('roles')->get();
        foreach ($users as $user) {
            foreach ($this->competencies->gapsFor($user, array_values($user->getRoleNames()->all())) as $gap) {
                $id = (int) $gap['competency']->id;
                $byCompetency[$id] ??= ['competency' => $gap['competency'], 'people' => 0, 'gap_sum' => 0];
                $byCompetency[$id]['people']++;
                $byCompetency[$id]['gap_sum'] += (int) $gap['gap'];
            }
        }
        $courses = LearningCourse::query()
            ->whereIn('competency_id', array_keys($byCompetency))
            ->where('status', LearningCourseStatus::Released->value)
            ->get(['id', 'title', 'competency_id'])
            ->groupBy('competency_id');

        $rows = [];
        foreach ($byCompetency as $id => $row) {
            $rows[] = [
                'competency' => (string) $row['competency']->name,
                'people' => $row['people'],
                'average_gap' => round($row['gap_sum'] / $row['people'], 1),
                'courses' => array_values($courses->get($id, collect())->pluck('title')->map(static fn (mixed $t): string => (string) $t)->all()),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$b['people'], $b['average_gap']] <=> [$a['people'], $a['average_gap']]);

        return $rows;
    }
}
