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
use App\Models\{Customer, Project, TimeEntry, Timesheet, User};
use App\Services\Material\MaterialProviderRegistry;
use App\Services\Timesheet\{Stopwatch, TimesheetResolver};
use App\Services\UI\DateRangeContext;
use App\Support\{Setting, SortableQuery};
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class TimesheetController extends Controller {
    public function __construct(
        private readonly TimesheetResolver $resolver,
        private readonly Stopwatch $stopwatch,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', Timesheet::class);

        $userId = (int) Auth::id();
        $scope = $request->string('scope', 'mine')->toString();
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();

        $globalRange = app(DateRangeContext::class)->current();
        $query = Timesheet::query()
            ->with(['project', 'user'])
            // Scope statt Inline-whereBetween: kennt den Randtag-Fall
            // (date-Cast speichert 'Y-m-d 00:00:00').
            ->inRange($globalRange['from'], $globalRange['to']);
        if ($scope !== 'team' || ! $isAdmin) {
            $query->forUser($userId);
        }

        $rawProject = (string) $request->query('project', '');
        $projectId = \App\Support\Sqid::decodeOrNumeric(Project::class, $rawProject);
        if ($projectId) {
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
            'timesheets' => $query->paginate((int) Setting::get('pagination.timesheets', 20))->withQueryString(),
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'selectedProjectSqid' => $projectId ? \App\Support\Sqid::encode(Project::class, $projectId) : null,
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

    public function store(Project $project, SaveTimesheetRequest $request): RedirectResponse|JsonResponse {
        Gate::authorize('create', Timesheet::class);

        $data = $request->validated();
        [$timesheet, $created] = $this->resolver->openOrCreate(
            $project,
            (int) Auth::id(),
            CarbonImmutable::parse((string) $data['work_date']),
            $data,
        );

        return $this->afterCreate($request, $project, $timesheet, $created);
    }

    /**
     * Weiterleitung nach dem Anlegen: direkt in den neuen Stundenzettel, mit
     * geöffnetem Zeilendialog (`add=entry`) — sonst endet die Anlage auf der
     * Ausgangsseite und der frische Zettel muss erst wieder gesucht werden.
     */
    private function afterCreate(Request $request, Project $project, Timesheet $timesheet, bool $created): RedirectResponse|JsonResponse {
        $target = route('projects.timesheets.show', [$project, $timesheet, 'add' => 'entry']);
        $message = $created
            ? __('Stundenzettel angelegt.')
            : __('Für diesen Tag gibt es bereits einen Stundenzettel — er ist jetzt geöffnet.');

        // Dialog-Submits laufen per fetch: einer 302 folgt nur der fetch selbst,
        // die JS-Schicht lädt danach die *Ausgangs*seite neu. Deshalb bekommt
        // sie das Ziel explizit als JSON (Muster wie ProjectController::update).
        if ($request->hasHeader('X-Entry-Dialog')) {
            $request->session()->flash('success', $message);

            return response()->json(['redirect' => $target]);
        }

        return redirect()->to($target)->with('success', $message);
    }

    /**
     * Schnell-Anlage eines Stundenzettels via Kunde (Toggl-Stil):
     * fällt automatisch auf das Standardprojekt des Kunden zurück, wenn
     * kein Projekt angegeben ist (z. B. ad-hoc / Notfall-Einsätze).
     */
    public function storeQuick(Request $request): RedirectResponse|JsonResponse {
        Gate::authorize('create', Timesheet::class);

        $rawCustomerId = $request->input('customer_id');
        $customerId = \App\Support\Sqid::decodeOrNumeric(\App\Models\Customer::class, $rawCustomerId);

        $rawProjectId = $request->input('project_id');
        $projectId = \App\Support\Sqid::decodeOrNumeric(\App\Models\Project::class, $rawProjectId);

        $request->merge([
            'customer_id' => $customerId,
            'project_id' => $projectId,
        ]);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'project_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
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
            ? CarbonImmutable::parse($data['work_date'])
            : CarbonImmutable::today();

        [$timesheet, $created] = $this->resolver->openOrCreate($project, (int) Auth::id(), $workDate);

        return $this->afterCreate($request, $project, $timesheet, $created);
    }

    public function show(Project $project, Timesheet $timesheet, MaterialProviderRegistry $registry): View {
        Gate::authorize('view', $timesheet);
        $timesheet->load(['entries.task', 'entries.tags:id,name,color', 'materialUsages.material', 'signatureAttachment']);

        $tasks = $project->tasks()->orderBy('title')->get(['id', 'title']);
        $materials = $registry->get('local')?->search('', 50) ?? collect();

        /** @var User $authUser */
        $authUser = Auth::user();

        return view('timesheets.show', [
            'project' => $project,
            'timesheet' => $timesheet,
            'tasks' => $tasks,
            'materials' => $materials,
            'runningEntry' => $this->stopwatch->current($authUser),
            // Zeiten desselben Tages, die an keinem Zettel hängen — der Knopf
            // „Zeiten übernehmen" erscheint nur, wenn es welche gibt.
            'adoptableCount' => TimeEntry::query()->adoptableFor($timesheet)->count(),
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
