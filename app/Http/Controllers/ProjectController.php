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

use App\Http\Requests\SaveProjectRequest;
use App\Models\DiaryEntry;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Project::class);

        $statusFilter = $request->string('status')->toString();
        $query = Project::query();
        if (in_array($statusFilter, Project::STATUSES, true)) {
            $query->where('status', $statusFilter);
        }

        $projects = $query->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->select(['id', 'name', 'slug', 'color', 'status', 'description', 'starts_on', 'ends_on', 'parent_id', 'customer_id'])
            ->with(['parent:id,name', 'customer:id,name,slug'])
            ->get();

        $projectIds = $projects->pluck('id')->all();

        // Einzel-Query für alle Aggregationen (statt 3 separater Queries)
        $aggr = DiaryEntry::query()
            ->select('project_id', 'status', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(start_at) as last_at'), DB::raw('COUNT(DISTINCT user_id) as user_cnt'))
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id', 'status')
            ->get()
            ->groupBy('project_id');

        // View-kompatible Strukturen aus einer einzigen Query ableiten
        $stats = $aggr;
        $lastEntries = $aggr->map(fn ($rows) => $rows->max('last_at'));
        $userCounts = $aggr->map(fn ($rows) => $rows->max('user_cnt'));

        return view('projects.index', [
            'projects' => $projects,
            'stats' => $stats,
            'lastEntries' => $lastEntries,
            'userCounts' => $userCounts,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function show(Project $project): View
    {
        Gate::authorize('view', $project);

        // Diary-Einträge (Tab 4)
        $entries = $project->diaryEntries()
            ->with(['user:id,name', 'tags:id,name,color'])
            ->orderByDesc('start_at')
            ->limit(20)
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
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Project::class);

        return view('projects._form_dialog', [
            'project' => null,
            'isDialog' => true,
        ]);
    }

    public function store(SaveProjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);

        $data = $request->validated();

        $project = Project::create($data + ['created_by' => Auth::id()]);

        return redirect()->route('projects.show', $project)
            ->with('success', __('Projekt angelegt.'));
    }

    public function edit(Request $request, Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects._form_dialog', [
            'project' => $project,
            'isDialog' => true,
        ]);
    }

    public function update(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validated();
        $project->update($data);

        return redirect()->route('projects.show', $project)
            ->with('success', __('Projekt aktualisiert.'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', __('Projekt gelöscht.'));
    }
}
