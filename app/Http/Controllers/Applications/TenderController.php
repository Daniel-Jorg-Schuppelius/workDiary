<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use App\Models\Applications\{ApplicationOpportunity, ApplicationRequirement};
use App\Models\{Customer, Project, User};
use App\Services\Applications\TenderService;
use App\Support\SortableQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Ausschreibungsakten / Auftragsbewerbungen (Feature 068, MVP-184–187):
 * Pipeline-Liste mit Frist-/Statusfiltern, Detailakte mit
 * Unterlagen-Checkliste, Einreichungspaketen, Go-/No-go und Entscheidung.
 */
class TenderController extends Controller {
    public function __construct(private readonly TenderService $tenders) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ApplicationOpportunity::class);

        $status = $request->string('status')->toString();
        $statusFilter = in_array($status, ApplicationOpportunity::STATUSES, true) ? $status : '';

        $query = ApplicationOpportunity::query()->with(['customer', 'responsible'])
            ->when($statusFilter !== '', fn($q) => $q->where('status', $statusFilter))
            ->when($request->boolean('open_only'), fn($q) => $q->whereIn('status', ApplicationOpportunity::OPEN_STATUSES));

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'title' => 'title',
            'status' => 'status',
            'submission_deadline' => 'submission_deadline',
            'estimated_value' => 'estimated_value',
        ], 'submission_deadline', 'asc');

        return view('applications.tenders.index', [
            'opportunities' => $query->paginate(25)->withQueryString(),
            'statuses' => ApplicationOpportunity::STATUSES,
            'filters' => ['status' => $statusFilter, 'open_only' => $request->boolean('open_only')],
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ApplicationOpportunity::class);

        return view('applications.tenders._form_dialog', [
            'opportunity' => new ApplicationOpportunity(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ApplicationOpportunity::class);
        $data = $this->validated($request);

        /** @var User $actor */
        $actor = Auth::user();
        $opportunity = ApplicationOpportunity::query()->create([
            ...$data,
            'organization_id' => (int) $actor->organization_id,
            'created_by' => $actor->id,
        ]);
        $opportunity->audit('tender.created', ['title' => $opportunity->title]);

        return redirect()->route('tenders.show', $opportunity)->with('status', __('Ausschreibungsakte angelegt.'));
    }

    public function show(ApplicationOpportunity $opportunity): View {
        Gate::authorize('view', $opportunity);
        $opportunity->load(['requirements.document', 'submissions', 'customer', 'project', 'responsible', 'negotiations.versions', 'negotiations.reviewItems', 'negotiations.approvals']);

        return view('applications.tenders.show', [
            'opportunity' => $opportunity,
            'projects' => Project::query()->when($opportunity->customer_id, fn($q) => $q->where('customer_id', $opportunity->customer_id))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(ApplicationOpportunity $opportunity): View {
        Gate::authorize('update', $opportunity);

        return view('applications.tenders._form_dialog', [
            'opportunity' => $opportunity,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('update', $opportunity);
        $opportunity->update($this->validated($request));

        return redirect()->route('tenders.show', $opportunity)->with('status', __('Akte aktualisiert.'));
    }

    public function destroy(ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('delete', $opportunity);
        $opportunity->requirements()->delete();
        $opportunity->delete();

        return redirect()->route('tenders.index')->with('status', __('Akte gelöscht.'));
    }

    // ── Unterlagen-Checkliste (MVP-185) ──────────────────────────────────

    public function addRequirement(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('update', $opportunity);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:300'],
            'kind' => ['required', 'in:document,proof,question,task'],
            'required' => ['nullable', 'boolean'],
            'due_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $opportunity->requirements()->create([
            'organization_id' => $opportunity->organization_id,
            'label' => $data['label'],
            'kind' => $data['kind'],
            'required' => (bool) ($data['required'] ?? true),
            'due_on' => $data['due_on'] ?? null,
            'note' => $data['note'] ?? null,
            'position' => (int) $opportunity->requirements()->max('position') + 1,
        ]);

        return back()->with('status', __('Anforderung hinzugefügt.'));
    }

    public function updateRequirement(Request $request, ApplicationOpportunity $opportunity, ApplicationRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $opportunity);
        abort_unless($requirement->application_opportunity_id === $opportunity->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,done,not_applicable'],
            'document_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('documents')],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $requirement->update($data);

        return back()->with('status', __('Anforderung aktualisiert.'));
    }

    public function removeRequirement(ApplicationOpportunity $opportunity, ApplicationRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $opportunity);
        abort_unless($requirement->application_opportunity_id === $opportunity->id, 404);
        $requirement->delete();

        return back()->with('status', __('Anforderung entfernt.'));
    }

    // ── Lifecycle (MVP-184/187) ──────────────────────────────────────────

    public function updateStatus(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('update', $opportunity);
        $data = $request->validate([
            'status' => ['required', 'in:captured,screened,in_progress,question,post_submission'],
        ]);
        if (! $opportunity->isOpen()) {
            return back()->with('error', __('Die Akte ist bereits entschieden.'));
        }
        $opportunity->update(['status' => $data['status']]);

        return back()->with('status', __('Status aktualisiert.'));
    }

    public function decideGo(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('decide', $opportunity);
        $data = $request->validate([
            'decision' => ['required', 'in:go,no_go'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->tenders->decideGo($opportunity, $data['decision'], $data['note'] ?? null, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Go-/No-go-Entscheidung dokumentiert.'));
    }

    public function submit(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('decide', $opportunity);
        $data = $request->validate([
            'channel' => ['required', 'in:portal,email,paper,other'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $submission = $this->tenders->submit($opportunity, $data['channel'], $data['note'] ?? null, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Einreichung V:version dokumentiert (SHA-256 :hash…).', [
            'version' => $submission->version,
            'hash' => substr($submission->sha256, 0, 12),
        ]));
    }

    public function decide(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('decide', $opportunity);
        $data = $request->validate([
            'decision' => ['required', 'in:won,lost,withdrawn'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->tenders->decide($opportunity, $data['decision'], $data['reason'] ?? null, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Entscheidung dokumentiert.'));
    }

    public function transfer(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('decide', $opportunity);
        $request->merge(['project_id' => \App\Support\Sqid::decodeOrNumeric(Project::class, $request->input('project_id'))]);
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
        ]);

        $existing = null;
        if (isset($data['project_id'])) {
            $existing = Project::query()->whereKey((int) $data['project_id'])->first();
        }

        try {
            $project = $this->tenders->transferToProject($opportunity, $existing, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('projects.show', $project)
            ->with('status', __('Ausschreibung in Projekt „:name" überführt.', ['name' => $project->name]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $request->merge([
            'customer_id' => \App\Support\Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
            'responsible_user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('responsible_user_id')),
        ]);

        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'kind' => ['required', 'in:' . implode(',', ApplicationOpportunity::KINDS)],
            'source' => ['nullable', 'string', 'max:200'],
            'customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'responsible_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'question_deadline' => ['nullable', 'date'],
            'submission_deadline' => ['nullable', 'date'],
            'decision_expected_on' => ['nullable', 'date'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'risk_note' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
