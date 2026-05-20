<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Timesheet\TimesheetStatus;
use App\Http\Requests\SaveTimesheetRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Material\MaterialProviderRegistry;
use App\Services\UI\DateRangeContext;
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
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();

        $globalRange = app(DateRangeContext::class)->current();
        $query = Timesheet::query()
            ->with(['project', 'user'])
            ->whereBetween('work_date', [
                $globalRange['from']->toDateString(),
                $globalRange['to']->toDateString(),
            ]);
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
            'timesheets' => $query->paginate((int) setting('pagination.timesheets', 20))->withQueryString(),
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * Projekt-Picker für die Sidebar-Aktion „Stundenzettel". Analog zum
     * Zeiteintrag-Picker — Stundenzettel sind immer projektgebunden.
     */
    public function pick(): View {
        Gate::authorize('create', Timesheet::class);

        return view('projects._picker_dialog', Project::pickerData() + [
            'targetRoute' => 'projects.timesheets.create',
            'title' => __('Stundenzettel anlegen'),
            'eyebrow' => __('Stundenzettel'),
            'icon' => 'description',
            'description' => __('Wähle ein Projekt, für das der Stundenzettel erstellt werden soll.'),
            'isDialog' => true,
        ]);
    }

    public function create(Project $project): View {
        Gate::authorize('create', Timesheet::class);

        return view('timesheets._form_dialog', [
            'project' => $project,
            'timesheet' => new Timesheet(['work_date' => CarbonImmutable::today()]),
        ]);
    }

    public function store(Project $project, SaveTimesheetRequest $request): RedirectResponse {
        Gate::authorize('create', Timesheet::class);

        $timesheet = $project->timesheets()->create($request->validated() + [
            'user_id' => Auth::id(),
            'organization_id' => $project->organization_id,
            'status' => TimesheetStatus::Draft->value,
        ]);

        return redirect()->route('projects.timesheets.show', [$project, $timesheet])
            ->with('success', __('Stundenzettel angelegt.'));
    }

    /**
     * Schnell-Anlage eines Stundenzettels via Kunde (Toggl-Stil):
     * fällt automatisch auf das Standardprojekt des Kunden zurück, wenn
     * kein Projekt angegeben ist (z. B. ad-hoc / Notfall-Einsätze).
     */
    public function storeQuick(Request $request): RedirectResponse {
        Gate::authorize('create', Timesheet::class);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'work_date' => ['nullable', 'date'],
        ]);

        /** @var Customer $customer */
        $customer = Customer::query()->findOrFail($data['customer_id']);

        if (isset($data['project_id'])) {
            /** @var Project $project */
            $project = Project::query()->where('customer_id', $customer->id)->findOrFail($data['project_id']);
        } else {
            $project = $customer->defaultProjectOrCreate();
        }

        $workDate = isset($data['work_date'])
            ? CarbonImmutable::parse($data['work_date'])->toDateString()
            : CarbonImmutable::today()->toDateString();

        /** @var Timesheet $timesheet */
        $timesheet = $project->timesheets()->create([
            'work_date' => $workDate,
            'user_id' => Auth::id(),
            'organization_id' => $project->organization_id,
            'status' => TimesheetStatus::Draft->value,
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

        return view('timesheets._form_dialog', [
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
        $timesheet->update(['status' => TimesheetStatus::Submitted->value]);

        return back()->with('success', __('Eingereicht.'));
    }
}
