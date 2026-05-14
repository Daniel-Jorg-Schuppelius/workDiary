<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTimesheetRequest;
use App\Models\Project;
use App\Models\Timesheet;
use App\Services\Material\MaterialProviderRegistry;
use App\Support\SortableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TimesheetController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Timesheet::class);

        $userId = (int) Auth::id();
        $scope = $request->string('scope', 'mine')->toString();
        $isAdmin = Auth::user()?->isAdmin() ?? false;

        $query = Timesheet::query()->with(['project', 'user']);
        if ($scope !== 'team' || ! $isAdmin) {
            $query->forUser($userId);
        }

        if ($projectId = $request->integer('project')) {
            $query->where('project_id', $projectId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'work_date' => 'work_date',
            'status' => 'status',
            'user_id' => 'user_id',
            'project_id' => 'project_id',
            'created_at' => 'created_at',
        ], 'work_date', 'desc');

        return view('timesheets.index', [
            'timesheets' => $query->paginate(20)->withQueryString(),
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Project $project): View {
        Gate::authorize('create', Timesheet::class);

        return view('timesheets.form', [
            'project' => $project,
            'timesheet' => new Timesheet(['work_date' => CarbonImmutable::today()]),
        ]);
    }

    public function store(Project $project, SaveTimesheetRequest $request): RedirectResponse {
        Gate::authorize('create', Timesheet::class);

        $timesheet = $project->timesheets()->create($request->validated() + [
            'user_id' => Auth::id(),
            'organization_id' => $project->organization_id,
            'status' => Timesheet::STATUS_DRAFT,
        ]);

        return redirect()->route('projects.timesheets.show', [$project, $timesheet])
            ->with('success', __('Stundenzettel angelegt.'));
    }

    public function show(Project $project, Timesheet $timesheet, MaterialProviderRegistry $registry): View {
        Gate::authorize('view', $timesheet);
        $timesheet->load(['entries.task', 'materialUsages.material', 'signatureAttachment']);

        $tasks = $project->tasks()->orderBy('title')->get(['id', 'title']);
        $materials = $registry->get('local')?->search('', 50) ?? collect();

        return view('timesheets.show', [
            'project' => $project,
            'timesheet' => $timesheet,
            'tasks' => $tasks,
            'materials' => $materials,
        ]);
    }

    public function edit(Project $project, Timesheet $timesheet): View {
        Gate::authorize('update', $timesheet);

        return view('timesheets.form', [
            'project' => $project,
            'timesheet' => $timesheet,
        ]);
    }

    public function update(Project $project, Timesheet $timesheet, SaveTimesheetRequest $request): RedirectResponse {
        Gate::authorize('update', $timesheet);
        $timesheet->update($request->validated());

        return redirect()->route('projects.timesheets.show', [$project, $timesheet])
            ->with('success', __('Stundenzettel aktualisiert.'));
    }

    public function destroy(Project $project, Timesheet $timesheet): RedirectResponse {
        Gate::authorize('delete', $timesheet);
        $timesheet->delete();

        return redirect()->route('projects.show', $project)
            ->with('success', __('Stundenzettel gelöscht.'));
    }

    public function submit(Project $project, Timesheet $timesheet): RedirectResponse {
        Gate::authorize('submit', $timesheet);
        $timesheet->update(['status' => Timesheet::STATUS_SUBMITTED]);

        return back()->with('success', __('Eingereicht.'));
    }
}
