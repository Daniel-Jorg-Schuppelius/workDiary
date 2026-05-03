<?php

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Project::class);

        $statusFilter = $request->string('status')->toString();
        $query = Project::query();
        if (in_array($statusFilter, Project::STATUSES, true)) {
            $query->where('status', $statusFilter);
        }

        $projects = $query->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get();

        $projectIds = $projects->pluck('id')->all();

        // Aggregat: Eintragszahlen pro Projekt + Statusbreakdown
        $stats = DiaryEntry::query()
            ->select('project_id', 'status', DB::raw('COUNT(*) as cnt'))
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id', 'status')
            ->get()
            ->groupBy('project_id');

        $lastEntries = DiaryEntry::query()
            ->select('project_id', DB::raw('MAX(start_at) as last_at'))
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->pluck('last_at', 'project_id');

        $userCounts = DiaryEntry::query()
            ->select('project_id', DB::raw('COUNT(DISTINCT user_id) as cnt'))
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->pluck('cnt', 'project_id');

        return view('projects.index', [
            'projects' => $projects,
            'stats' => $stats,
            'lastEntries' => $lastEntries,
            'userCounts' => $userCounts,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function show(Project $project): View {
        Gate::authorize('view', $project);

        $entries = $project->diaryEntries()
            ->with(['user:id,name', 'tags:id,name,color'])
            ->orderByDesc('start_at')
            ->limit(50)
            ->get();

        return view('projects.show', [
            'project' => $project,
            'entries' => $entries,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', Project::class);

        $isDialog = $request->boolean('dialog');

        return view($isDialog ? 'projects._form_dialog' : 'projects.form', [
            'project' => null,
            'isDialog' => $isDialog,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', Project::class);

        $data = $this->validatePayload($request);

        $project = Project::create($data + ['created_by' => Auth::id()]);

        return redirect()->route('projects.show', $project)
            ->with('success', __('Projekt angelegt.'));
    }

    public function edit(Request $request, Project $project): View {
        Gate::authorize('update', $project);

        $isDialog = $request->boolean('dialog');

        return view($isDialog ? 'projects._form_dialog' : 'projects.form', [
            'project' => $project,
            'isDialog' => $isDialog,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse {
        Gate::authorize('update', $project);

        $data = $this->validatePayload($request, $project);
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

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?Project $project = null): array {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('projects', 'name')->ignore($project?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:16'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
    }
}
