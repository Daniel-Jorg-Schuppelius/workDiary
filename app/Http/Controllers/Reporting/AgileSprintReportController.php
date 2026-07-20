<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSprintReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Agile\{AgileBoard, AgileSprint};
use App\Models\{Milestone, Project};
use App\Services\Agile\{AgileMetricsService, AgileWorkItemService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Sprint-Cockpit (Feature 064, P8/MVP-146): Burndown/Burnup aus Events,
 * Velocity + Commitment-Erfüllung aus Snapshots, Scope-Zusammensetzung,
 * Qualitätsreihe (Wiederöffnungen/Overrides), Sprintabschlussbericht
 * (Snapshot-basiert, unveränderlich), Meilenstein-Fortschritt. Berechnung
 * NUR über AgileMetricsService, Rendering über x-charts.*-Komponenten.
 */
class AgileSprintReportController extends Controller {
    public function __construct(
        private readonly AgileMetricsService $metrics,
        private readonly AgileWorkItemService $items,
    ) {}

    public function index(Request $request, Project $project): View {
        Gate::authorize(Permission::AgileReportView->value);
        Gate::authorize('view', $project);

        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();

        $sprints = AgileSprint::query()
            ->where('board_id', $board->id)
            ->orderByDesc('id')
            ->get();

        // Gewählter Sprint: ?sprint=sqid, sonst aktiver, sonst letzter gestarteter.
        $sprint = null;
        if (trim((string) $request->query('sprint', '')) !== '') {
            $sprintId = \App\Support\Sqid::decode(AgileSprint::class, (string) $request->query('sprint'));
            $sprint = $sprints->firstWhere('id', $sprintId) ?? abort(404);
        }
        $sprint ??= $sprints->firstWhere('status', AgileSprint::STATUS_ACTIVE)
            ?? $sprints->first(fn(AgileSprint $s): bool => $s->started_at !== null);

        $burndown = $sprint?->started_at !== null ? $this->metrics->burndown($sprint) : null;

        // Burnup aus derselben Reihe: erledigt = Umfang − verbleibend.
        $burnup = null;
        if ($burndown !== null) {
            $burnup = array_map(fn(array $row): array => [
                'x' => $row['date'],
                'y' => (int) $burndown->data['committed'] + $row['scope_delta'] - $row['remaining'],
                'y2' => (int) $burndown->data['committed'] + $row['scope_delta'],
            ], (array) $burndown->data['series']);
        }

        // Scope-Zusammensetzung des gewählten Sprints.
        $scope = null;
        if ($sprint !== null) {
            $assignments = $sprint->items()->with('workItem')->get();
            $added = $assignments->where('added_after_start', true);
            $points = fn($set): int => (int) $set->sum(fn($a): int => (int) ($a->workItem->story_points ?? 0));
            $scope = [
                'committed_items' => $assignments->count() - $added->count(),
                'committed_points' => $points($assignments) - $points($added),
                'added_items' => $added->count(),
                'added_points' => $points($added),
            ];
        }

        return view('agile.reports.sprint', [
            'project' => $project,
            'board' => $board,
            'sprints' => $sprints,
            'sprint' => $sprint,
            'burndown' => $burndown,
            'burnup' => $burnup,
            'scope' => $scope,
            'velocity' => $this->metrics->velocity($board),
            'quality' => $this->metrics->qualitySeries($board),
            // Vollaudit 2026-07 (M25): Epic-Fortschritt (MVP-146).
            'epicProgress' => $this->items->epicProgress($board),
            'milestones' => Milestone::query()
                ->where('project_id', $project->id)
                ->withCount(['tasks', 'tasks as done_tasks_count' => fn($q) => $q->where('status', 'done')])
                ->orderBy('due_date')
                ->get(),
        ]);
    }
}
