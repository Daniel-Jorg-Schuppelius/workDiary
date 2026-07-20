<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerStatsService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services;

use App\Models\{Customer, TimeEntry};
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Aggregiert Zeiteinträge pro Kunde — projektübergreifend (Toggl-Stil).
 * Quelle der Wahrheit: `time_entries.minutes`. Stundenzettel sind Bündel,
 * aber jede Minute landet immer auch in `time_entries`.
 */
class CustomerStatsService {
    /**
     * @return array{
     *     total_minutes: int,
     *     billable_minutes: int,
     *     by_project: array<int, array{project_id: int, name: string, is_default: bool, foreign_customer: ?string, minutes: int, billable_minutes: int}>
     * }
     */
    public function forCustomer(Customer $customer, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array {
        $projectIds = $customer->projects()->pluck('id')->all();
        if ($projectIds === []) {
            return ['total_minutes' => 0, 'billable_minutes' => 0, 'by_project' => []];
        }

        $query = TimeEntry::query()->whereIn('project_id', $projectIds);
        if ($from instanceof CarbonInterface) {
            $query->where('date', '>=', $from->toDateString());
        }
        if ($to instanceof CarbonInterface) {
            $query->where('date', '<=', $to->toDateString());
        }

        /** @var array<int, object{project_id: int, mins: int, billable_mins: int}> $rows */
        $rows = $query->select([
            'project_id',
            DB::raw('COALESCE(SUM(minutes), 0) as mins'),
            DB::raw('COALESCE(SUM(CASE WHEN billable = 1 THEN minutes ELSE 0 END), 0) as billable_mins'),
        ])->groupBy('project_id')->get()->all();

        $byProjectMins = [];
        foreach ($rows as $row) {
            $byProjectMins[(int) $row->project_id] = [
                'minutes' => (int) $row->mins,
                'billable_minutes' => (int) $row->billable_mins,
            ];
        }

        $byProject = [];
        $total = 0;
        $billable = 0;
        foreach ($customer->projects()->with('foreignCustomer:id,name')->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default', 'foreign_customer_id']) as $project) {
            $stats = $byProjectMins[$project->id] ?? ['minutes' => 0, 'billable_minutes' => 0];
            $byProject[] = [
                'project_id' => (int) $project->id,
                'name' => (string) $project->name,
                'is_default' => (bool) $project->is_default,
                // Endkunde zur Unterscheidung gleichnamiger Projekte (Toggl-Import).
                'foreign_customer' => $project->foreignCustomer?->name,
                'minutes' => $stats['minutes'],
                'billable_minutes' => $stats['billable_minutes'],
            ];
            $total += $stats['minutes'];
            $billable += $stats['billable_minutes'];
        }

        return [
            'total_minutes' => $total,
            'billable_minutes' => $billable,
            'by_project' => $byProject,
        ];
    }
}
