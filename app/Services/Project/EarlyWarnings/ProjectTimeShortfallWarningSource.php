<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\EarlyWarnings;

use App\Models\Platform\Organization;
use App\Models\Project\{Project, ProjectTimeShortfall};
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Support\Query\DateRange;
use App\Support\Tz;
use Carbon\CarbonImmutable;

/** Frühwarnung (Feature 194, MVP-1469): Häufung offener Befunde unter Soll je Projekt in den letzten sieben Tagen. */
final class ProjectTimeShortfallWarningSource implements EarlyWarningSource {
    public const DAYS = 7;

    public const THRESHOLD = 3;

    public function key(): string {
        return 'project_target';
    }

    public function warnings(Organization $organization): array {
        $today = CarbonImmutable::now(Tz::ofOrganization($organization))->startOfDay();
        $from = $today->subDays(self::DAYS - 1);
        $counts = ProjectTimeShortfall::query()
            ->where('organization_id', $organization->id)
            ->open()
            ->whereBetween('date', DateRange::days($from, $today))
            ->groupBy('project_id')
            ->selectRaw('project_id, COUNT(*) as open_days')
            ->toBase()
            ->get()
            ->filter(static fn (object $row): bool => (int) $row->open_days >= self::THRESHOLD)
            ->keyBy(static fn (object $row): int => (int) $row->project_id);
        if ($counts->isEmpty()) {
            return [];
        }
        $warnings = [];
        foreach (Project::query()->whereIn('id', $counts->keys()->all())->orderBy('name')->get() as $project) {
            $warnings[] = new EarlyWarning(
                kind: 'project_target',
                subject: $project,
                title: 'reporting.warning.project_target.title',
                detail: 'reporting.warning.project_target.detail',
                recommendation: 'reporting.warning.project_target.recommendation',
                url: route('projects.show', ['project' => $project, 'tab' => 'time', 'show' => 'shortfalls']),
                params: ['name' => $project->name, 'count' => (int) ($counts->get($project->id)->open_days ?? 0), 'days' => self::DAYS],
            );
        }

        return $warnings;
    }
}
