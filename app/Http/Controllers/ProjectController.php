<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Project\ProjectStatus;
use App\Http\Requests\SaveProjectRequest;
use App\Models\{DiaryEntry, Project, RecurrenceRule};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\{LengthAwarePaginator, Paginator};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;

class ProjectController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Project::class);

        $statusFilter = $request->string('status')->toString();
        $query = Project::query();
        if (in_array($statusFilter, ProjectStatus::values(), true)) {
            $query->where('status', $statusFilter);
        }

        $projects = $query->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->select(['id', 'name', 'slug', 'color', 'status', 'description', 'starts_on', 'ends_on', 'parent_id', 'customer_id'])
            ->with(['parent:id,name', 'customer:id,name,slug'])
            ->get();

        // Hierarchische, flache Zeilenliste aufbauen: Wurzel-Projekte (oder Waisen,
        // deren Parent nicht im gefilterten Set liegt) gefolgt von ihren Kindern.
        $byId = $projects->keyBy('id');
        $childrenByParent = $projects->groupBy(fn(Project $p): int => $p->parent_id ?? 0);

        $roots = $projects
            ->filter(fn(Project $p) => $p->parent_id === null || ! $byId->has($p->parent_id))
            ->values();

        // Pagination auf Ebene der Wurzel-Projekte, damit Bäume nicht zerschnitten werden.
        $perPage = 25;
        $page = Paginator::resolveCurrentPage();
        $pageRoots = $roots->forPage($page, $perPage);

        $rows = collect();
        $emit = function (Project $project, int $depth) use (&$emit, $childrenByParent, $rows): void {
            $rows->push(['project' => $project, 'depth' => min($depth, 2)]);
            foreach ($childrenByParent->get($project->id, collect()) as $child) {
                $emit($child, $depth + 1);
            }
        };
        foreach ($pageRoots as $root) {
            $emit($root, 0);
        }

        $projectsPaginator = new LengthAwarePaginator(
            $pageRoots->values(),
            $roots->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Aggregationen nur für die tatsächlich sichtbaren Projekte berechnen.
        $projectIds = $rows->pluck('project.id')->all();

        // Einzel-Query für alle Aggregationen (statt 3 separater Queries)
        $aggr = DiaryEntry::query()
            ->select('project_id', 'status', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(start_at) as last_at'), DB::raw('COUNT(DISTINCT user_id) as user_cnt'))
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id', 'status')
            ->get()
            ->groupBy('project_id');

        // View-kompatible Strukturen aus einer einzigen Query ableiten
        $stats = $aggr;
        $lastEntries = $aggr->map(fn(Collection $rows) => $rows->max('last_at'));
        $userCounts = $aggr->map(fn(Collection $rows) => $rows->max('user_cnt'));

        return view('projects.index', [
            'rows' => $rows,
            'projects' => $projectsPaginator,
            'stats' => $stats,
            'lastEntries' => $lastEntries,
            'userCounts' => $userCounts,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function show(Project $project): View {
        Gate::authorize('view', $project);

        // Aufträge (Tab 4): alle DiaryEntries, die das Projekt entweder als
        // Initialprojekt haben ODER über mind. einen TimeEntry mit diesem
        // Projekt verknüpft sind. Sortierung nach letzter Aktivität —
        // Backlog/Deadline/Window haben kein start_at.
        $entries = DiaryEntry::query()
            ->with(['user:id,name', 'tags:id,name,color'])
            ->where(function ($q) use ($project): void {
                $q->where('project_id', $project->id)
                    ->orWhereIn('id', function ($sub) use ($project): void {
                        $sub->select('diary_entry_id')
                            ->from('time_entries')
                            ->where('project_id', $project->id)
                            ->whereNotNull('diary_entry_id');
                    });
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        // Milestones mit Tasks (Tab 1 + 2)
        $milestones = $project->milestones()
            ->with(['tasks' => function ($q): void {
                $q->whereNull('parent_task_id')->orderBy('position');
            }])
            ->get();

        // Alle Toplevel-Tasks des Projekts (Tab 2)
        $topTasks = $project->tasks()
            ->with(['assignee:id,name', 'milestone:id,title', 'subTasks'])
            ->whereNull('parent_task_id')
            ->orderBy('milestone_id')
            ->orderBy('position')
            ->get();

        // Task-Statistik (Tab 1)
        $taskStats = $project->tasks()
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // Zeiteinträge (Tab 3)
        $timeEntries = $project->timeEntries()
            ->with(['user:id,name', 'task:id,title'])
            ->orderByDesc('date')
            ->limit(100)
            ->get();

        // Zeit-Aggregationen (Tab 1 + 3)
        $totalMinutes = $project->timeEntries()->sum('minutes');
        $monthMinutes = $project->timeEntries()
            ->where('date', '>=', now()->startOfMonth())
            ->sum('minutes');
        $myMinutes = $project->timeEntries()
            ->where('user_id', Auth::id())
            ->sum('minutes');

        // Nächster Milestone für Übersicht
        $nextMilestone = $project->milestones()
            ->where('is_completed', false)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->first();

        $recurrenceRules = RecurrenceRule::query()
            ->where('project_id', $project->id)
            ->orderBy('name')
            ->get();

        return view('projects.show', [
            'project' => $project,
            'entries' => $entries,
            'milestones' => $milestones,
            'topTasks' => $topTasks,
            'taskStats' => $taskStats,
            'timeEntries' => $timeEntries,
            'totalMinutes' => (int) $totalMinutes,
            'monthMinutes' => (int) $monthMinutes,
            'myMinutes' => (int) $myMinutes,
            'nextMilestone' => $nextMilestone,
            'timesheets' => $project->timesheets()->with('user:id,name')->latest('work_date')->limit(50)->get(),
            'recurrenceRules' => $recurrenceRules,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', Project::class);

        return view('projects._form_dialog', [
            'project' => null,
            'isDialog' => true,
        ]);
    }

    public function store(SaveProjectRequest $request): RedirectResponse {
        Gate::authorize('create', Project::class);

        $data = $request->validated();

        $project = Project::create($data + ['created_by' => Auth::id()]);

        return redirect()->route('projects.show', $project)
            ->with('success', __('Projekt angelegt.'));
    }

    public function edit(Request $request, Project $project): View {
        Gate::authorize('update', $project);

        return view('projects._form_dialog', [
            'project' => $project,
            'isDialog' => true,
        ]);
    }

    public function update(SaveProjectRequest $request, Project $project): RedirectResponse {
        Gate::authorize('update', $project);

        $data = $request->validated();
        $project->update($data);

        return redirect()->route('projects.show', $project)
            ->with('success', __('Projekt aktualisiert.'));
    }

    public function destroy(Project $project): RedirectResponse {
        Gate::authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', __('Projekt gelöscht.'));
    }
}
