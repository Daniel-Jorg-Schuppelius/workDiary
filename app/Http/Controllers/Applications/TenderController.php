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

use App\Enums\Applications\TenderProcedureType;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Applications\{ApplicationOpportunity, ApplicationRequirement, TenderCompetitorBid};
use App\Models\{Customer, Project, User};
use App\Services\Applications\{TenderService, TenderSubmissionPreflight};
use App\Support\SortableQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;

/**
 * Ausschreibungsakten / Auftragsbewerbungen (Feature 068, MVP-184–187):
 * Pipeline-Liste mit Frist-/Statusfiltern, Detailakte mit
 * Unterlagen-Checkliste, Einreichungspaketen, Go-/No-go und Entscheidung.
 */
class TenderController extends Controller {
    use ResolvesCurrentOrganization;

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
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => $actor->id,
        ]);
        $opportunity->audit('tender.created', ['title' => $opportunity->title]);

        return redirect()->route('tenders.show', $opportunity)->with('success', __('Ausschreibungsakte angelegt.'));
    }

    public function show(ApplicationOpportunity $opportunity): View {
        Gate::authorize('view', $opportunity);
        $opportunity->load(['requirements.document', 'submissions', 'customer', 'project', 'responsible', 'competitorBids', 'negotiations.versions', 'negotiations.reviewItems', 'negotiations.approvals']);

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

        return redirect()->route('tenders.show', $opportunity)->with('success', __('Akte aktualisiert.'));
    }

    public function destroy(ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('delete', $opportunity);
        $opportunity->requirements()->delete();
        $opportunity->delete();

        return redirect()->route('tenders.index')->with('success', __('Akte gelöscht.'));
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

        return back()->with('success', __('Anforderung hinzugefügt.'));
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

        return back()->with('success', __('Anforderung aktualisiert.'));
    }

    public function removeRequirement(ApplicationOpportunity $opportunity, ApplicationRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $opportunity);
        abort_unless($requirement->application_opportunity_id === $opportunity->id, 404);
        $requirement->delete();

        return back()->with('success', __('Anforderung entfernt.'));
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

        return back()->with('success', __('Status aktualisiert.'));
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

        return back()->with('success', __('Go-/No-go-Entscheidung dokumentiert.'));
    }

    /**
     * Geführte Abgabe (MVP-628): Preflight, Ausgabewege und Dokumentation der
     * Einreichung auf einer Seite.
     *
     * Ein Angebot lässt sich nach der Abgabe nicht mehr reparieren — deshalb
     * steht die Prüfung **vor** dem Absenden, nicht als Fehlermeldung danach.
     */
    public function submitWizard(ApplicationOpportunity $opportunity, TenderSubmissionPreflight $preflight): View {
        Gate::authorize('decide', $opportunity);
        $opportunity->load(['requirements', 'submissions', 'billOfQuantity']);

        $findings = $preflight->check($opportunity);

        return view('applications.tenders.submit', [
            'opportunity' => $opportunity,
            'findings' => $findings,
            'blocked' => $preflight->isBlocked($findings),
        ]);
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

        return back()->with('success', __('Einreichung V:version dokumentiert (SHA-256 :hash…).', [
            'version' => $submission->version,
            'hash' => substr($submission->sha256, 0, 12),
        ]));
    }

    // ── Submissionsergebnis (MVP-628) ────────────────────────────────────

    /**
     * Ein im Eröffnungstermin verlesenes Angebot festhalten.
     *
     * Der Bieter bleibt Freitext: Wer verlesen wird, ist selten Stammdatensatz,
     * und eine Verknüpfung machte ihn zum Geschäftspartner, der er nicht ist.
     */
    public function addCompetitorBid(Request $request, ApplicationOpportunity $opportunity): RedirectResponse {
        Gate::authorize('update', $opportunity);
        $data = $request->validate([
            'bidder_name' => ['required', 'string', 'max:300'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'rank' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_own' => ['nullable', 'boolean'],
            'is_winner' => ['nullable', 'boolean'],
            'recorded_on' => ['nullable', 'date'],
            'source' => ['required', Rule::in(TenderCompetitorBid::SOURCES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $opportunity->competitorBids()->create([
            'organization_id' => $opportunity->organization_id,
            'bidder_name' => $data['bidder_name'],
            'amount' => $data['amount'] ?? null,
            'rank' => $data['rank'] ?? null,
            'is_own' => $request->boolean('is_own'),
            'is_winner' => $request->boolean('is_winner'),
            'recorded_on' => $data['recorded_on'] ?? null,
            'source' => $data['source'],
            'note' => $data['note'] ?? null,
            'created_by' => $this->actor()->id,
        ]);

        $opportunity->audit('tender.bid_recorded', ['bidder' => $data['bidder_name']]);

        return back()->with('success', __('Angebot festgehalten.'));
    }

    public function removeCompetitorBid(ApplicationOpportunity $opportunity, TenderCompetitorBid $bid): RedirectResponse {
        Gate::authorize('update', $opportunity);
        abort_unless($bid->application_opportunity_id === $opportunity->id, 404);

        $bid->delete();

        return back()->with('success', __('Angebot entfernt.'));
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

        return back()->with('success', __('Entscheidung dokumentiert.'));
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
            ->with('success', __('Ausschreibung in Projekt „:name" überführt.', ['name' => $project->name]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $request->merge([
            'customer_id' => \App\Support\Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
            'responsible_user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('responsible_user_id')),
        ]);

        // CPV-Codes kommen als Komma-Liste aus dem Formular; leere Einträge
        // fallen weg, statt als Fehler zu erscheinen.
        if (is_string($request->input('cpv_codes'))) {
            $request->merge(['cpv_codes' => array_values(array_filter(array_map(
                trim(...),
                explode(',', (string) $request->input('cpv_codes'))
            )))]);
        }

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

            // Vergabevorgang (MVP-625). Die Verfahrensart wird gegen die
            // Schwellenwertlage geprüft: Eine oberschwellige Vergabe im
            // unterschwelligen Verfahren ist angreifbar.
            'awarding_body' => ['nullable', 'string', 'max:200'],
            'procedure_no' => ['nullable', 'string', 'max:60'],
            'procedure_type' => ['nullable', Rule::enum(TenderProcedureType::class)],
            'above_threshold' => ['nullable', 'boolean'],
            'lot_no' => ['nullable', 'string', 'max:40'],
            'lot_group' => ['nullable', 'string', 'max:60'],
            'cpv_codes' => ['nullable', 'array', 'max:20'],
            'cpv_codes.*' => ['string', 'regex:/^\d{8}(-\d)?$/'],
            'nuts_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}[0-9A-Z]{0,3}$/'],
            'platform' => ['nullable', 'string', 'max:80'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'notice_url' => ['nullable', 'url', 'max:2000'],
            'participation_deadline' => ['nullable', 'date'],
            'opening_at' => ['nullable', 'date'],
            'binding_until' => ['nullable', 'date'],
        ]);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
