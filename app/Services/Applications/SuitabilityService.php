<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SuitabilityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications;

use App\Models\Applications\{JobApplication, JobApplicationRating, JobRequisition};
use App\Models\Learning\{Competency, CompetencyRequirement};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eignungsmatrix (MVP-924): Kompetenz-Soll je Stellenanforderung über den
 * Baustein `CompetencyRequirement` (Bezugsart `job`), Einschätzung je
 * Bewerbung, Matrix mit Lücken und Erfüllungsgrad. Eine Einschätzung ist eine
 * Hilfe zur Auswahl, keine automatische Entscheidung.
 */
final class SuitabilityService {
    public const SUBJECT_KIND = 'job';

    /** @return Collection<int, CompetencyRequirement> */
    public function requirements(JobRequisition $requisition): Collection {
        return CompetencyRequirement::query()
            ->with('competency')
            ->where('subject_kind', self::SUBJECT_KIND)
            ->where('subject_key', (string) $requisition->id)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (CompetencyRequirement $r): string => (string) $r->competency?->name)
            ->values();
    }

    public function setRequirement(JobRequisition $requisition, Competency $competency, int $level): CompetencyRequirement {
        return CompetencyRequirement::query()->updateOrCreate(
            ['organization_id' => $requisition->organization_id, 'competency_id' => $competency->id, 'subject_kind' => self::SUBJECT_KIND, 'subject_key' => (string) $requisition->id],
            ['required_level' => $competency->clampLevel($level), 'is_active' => true],
        );
    }

    public function removeRequirement(JobRequisition $requisition, CompetencyRequirement $requirement): void {
        abort_unless($requirement->subject_kind === self::SUBJECT_KIND && $requirement->subject_key === (string) $requisition->id, 404);
        $requirement->delete();
    }

    public function rate(JobApplication $application, Competency $competency, int $level, ?string $note, User $actor): JobApplicationRating {
        return JobApplicationRating::query()->updateOrCreate(
            ['job_application_id' => $application->id, 'competency_id' => $competency->id],
            [
                'organization_id' => $application->organization_id,
                'level' => $competency->clampLevel($level),
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'rated_by' => $actor->id,
            ],
        );
    }

    /**
     * @return array{requirements: Collection<int, CompetencyRequirement>, rows: list<array{application: JobApplication, levels: array<int, int>, gaps: int, score: ?int}>}
     */
    public function matrix(JobRequisition $requisition): array {
        $requirements = $this->requirements($requisition);
        $applications = JobApplication::query()
            ->where('job_requisition_id', $requisition->id)
            ->whereNull('anonymized_at')
            ->with('ratings')
            ->orderByDesc('id')
            ->get();
        $required = (int) $requirements->sum('required_level');

        $rows = [];
        foreach ($applications as $application) {
            $levels = $application->ratings->mapWithKeys(fn (JobApplicationRating $r): array => [$r->competency_id => $r->level])->all();
            $gaps = 0;
            $reached = 0;
            foreach ($requirements as $requirement) {
                $level = $levels[$requirement->competency_id] ?? 0;
                $gaps += $level < $requirement->required_level ? 1 : 0;
                $reached += min($level, $requirement->required_level);
            }
            $rows[] = ['application' => $application, 'levels' => $levels, 'gaps' => $gaps, 'score' => $required > 0 ? intdiv(100 * $reached, $required) : null];
        }
        usort($rows, static fn (array $a, array $b): int => ($b['score'] ?? -1) <=> ($a['score'] ?? -1));

        return ['requirements' => $requirements, 'rows' => $rows];
    }
}
