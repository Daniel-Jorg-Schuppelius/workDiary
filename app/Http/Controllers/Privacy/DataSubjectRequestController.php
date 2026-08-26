<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Enums\Privacy\{DataSubjectKind, DataSubjectRequestType};
use App\Http\Controllers\Controller;
use App\Models\Applications\JobApplication;
use App\Models\{AuditLog, Customer, Lead, Supplier, User};
use App\Models\Privacy\DataSubjectRequest;
use App\Services\Privacy\{DataSubjectRequestService, PrivacyExportService, SubjectDataExporter};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Bearbeitung von Betroffenenanfragen (DSGVO Art. 15–21). Alle Aktionen sind
 * organisationsgebunden und ueber die {@see \App\Policies\Privacy\DataSubjectRequestPolicy}
 * abgesichert; das harte Modul-Gate (EnforcePlanModules) sperrt zusaetzlich.
 */
class DataSubjectRequestController extends Controller {
    public function __construct(
        private readonly DataSubjectRequestService $service,
        private readonly PrivacyExportService $exporter,
        private readonly SubjectDataExporter $subjectExporter,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', DataSubjectRequest::class);

        $requests = DataSubjectRequest::query()
            ->latest('received_at')
            ->paginate(20);

        return view('privacy.requests.index', ['requests' => $requests]);
    }

    public function create(): View {
        Gate::authorize('create', DataSubjectRequest::class);

        return view('privacy.requests._form_dialog', ['types' => DataSubjectRequestType::cases()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', DataSubjectRequest::class);
        $org = $request->user()?->organization;
        abort_unless($org !== null, 403);

        $data = $request->validate([
            'type' => ['required', \Illuminate\Validation\Rule::enum(DataSubjectRequestType::class)],
            'subject' => ['required', 'string', 'max:2000'],
            'content' => ['required', 'string', 'max:20000'],
            'channel' => ['nullable', 'string', 'max:32'],
        ]);

        $dsr = $this->service->open(
            $org,
            DataSubjectRequestType::from($data['type']),
            $data['subject'],
            $data['content'],
            $data['channel'] ?? null,
            $request->user(),
        );

        return redirect()->route('dataprotection.requests.show', $dsr)
            ->with('status', __('Betroffenenanfrage angelegt.'));
    }

    // Der Routenparameter heisst durchgaengig {dsr}: Laravels implizites
    // Model-Binding greift NUR bei gleichnamigem Methodenparameter — mit
    // {request} injizierte der Container in verifyIdentity/assign/decide/export
    // ein LEERES Modell (Vollscan-Welle 8, aufgefallen bei MVP-728).
    public function show(DataSubjectRequest $dsr): View {
        Gate::authorize('view', $dsr);

        return view('privacy.requests.show', [
            'request' => $dsr->load('assignedUser'),
            'events' => $dsr->events()->get(),
            'members' => User::query()
                ->where('organization_id', $dsr->organization_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'subjectPickers' => Gate::allows('export', $dsr) ? $this->subjectPickers($dsr) : [],
        ]);
    }

    /**
     * Org-gescopte Auswahllisten je Betroffenenart für „Auskunft erzeugen"
     * (Feature 129). Bewusst nur Sqid + Anzeigename — keine weiteren PII.
     *
     * @return array<string, list<array{sqid: string, label: string}>>
     */
    private function subjectPickers(DataSubjectRequest $dsr): array {
        $orgId = (int) $dsr->organization_id;
        $users = User::query()->where('organization_id', $orgId)->orderBy('name')->get(['id', 'name', 'customer_id']);

        $option = static fn(string $sqid, ?string $label): array => ['sqid' => $sqid, 'label' => trim((string) $label) !== '' ? (string) $label : '—'];

        return [
            DataSubjectKind::User->value => array_values($users->whereNull('customer_id')
                ->map(fn(User $u): array => $option($u->sqid, $u->name))->all()),
            DataSubjectKind::PortalUser->value => array_values($users->whereNotNull('customer_id')
                ->map(fn(User $u): array => $option($u->sqid, $u->name))->all()),
            DataSubjectKind::Customer->value => array_values(Customer::query()->withoutGlobalScopes()
                ->where('organization_id', $orgId)->orderBy('name')->get(['id', 'name', 'company'])
                ->map(fn(Customer $c): array => $option($c->sqid, $c->name ?: $c->company))->all()),
            DataSubjectKind::Supplier->value => array_values(Supplier::query()->withoutGlobalScopes()
                ->where('organization_id', $orgId)->orderBy('name')->get(['id', 'name', 'company'])
                ->map(fn(Supplier $s): array => $option($s->sqid, $s->name ?: $s->company))->all()),
            DataSubjectKind::Lead->value => array_values(Lead::query()->withoutGlobalScopes()
                ->where('organization_id', $orgId)->orderBy('id')->get()
                ->map(fn(Lead $l): array => $option($l->sqid, $l->displayName()))->all()),
            DataSubjectKind::JobApplication->value => array_values(JobApplication::query()->withoutGlobalScopes()
                ->where('organization_id', $orgId)->orderBy('id')->get()
                ->map(fn(JobApplication $a): array => $option($a->sqid, $a->candidate_name))->all()),
        ];
    }

    /**
     * „Auskunft erzeugen" (Art. 15/20, Feature 129): baut das Auskunftspaket
     * für den gewählten Betroffenen und legt es DEK-verschlüsselt am Fall ab.
     */
    public function generateSubjectExport(Request $request, DataSubjectRequest $dsr): RedirectResponse {
        Gate::authorize('export', $dsr);

        // Pflichtschritt vor der Auskunft bei Portal-Eingaengen (G11, MVP-728):
        // dort steht hinter der Anfrage ein ungeprueftes Gegenueber, das sich
        // selbst benannt hat. Art. 12 Abs. 6 DSGVO erlaubt genau dafuer die
        // Nachforderung von Identitaetsnachweisen — ohne dokumentierte Pruefung
        // gibt es kein Auskunftspaket. Intern erfasste Faelle (Telefon, Post,
        // Schalter) hat eine Person bereits gesehen und bleiben unberuehrt.
        if ($dsr->isFromPortal() && $dsr->identity_verified_at === null) {
            return back()->withErrors([
                'identity' => __('dsar.internal.identity_required'),
            ]);
        }

        $data = $request->validate([
            'subject_type' => ['required', \Illuminate\Validation\Rule::enum(DataSubjectKind::class)],
            'subject_id' => ['required', 'string', 'max:64'],
        ]);

        $kind = DataSubjectKind::from($data['subject_type']);
        $id = Sqid::decodeOrNumeric($kind->modelClass(), $data['subject_id']);
        abort_unless($id !== null, 404);

        $subject = $this->subjectExporter->resolve($kind, (int) $dsr->organization_id, (int) $id);
        $payload = $this->subjectExporter->build($dsr, $kind, $subject);
        $this->subjectExporter->attachFiles($dsr, $kind, $payload, $request->user(), $subject);

        $this->audit($request, 'privacy.subjectExportGenerated', DataSubjectRequest::class, (int) $dsr->id);

        return back()->with('status', __('Auskunftspaket erzeugt und am Fall abgelegt.'));
    }

    public function verifyIdentity(DataSubjectRequest $dsr): RedirectResponse {
        Gate::authorize('update', $dsr);
        $this->service->verifyIdentity($dsr, Auth::user());

        return back()->with('status', __('Identität bestätigt.'));
    }

    public function assign(Request $request, DataSubjectRequest $dsr): RedirectResponse {
        Gate::authorize('assign', $dsr);
        // Sqid-Input dekodieren (numerischer Fallback für Alt-Clients).
        $request->merge(['user_id' => Sqid::decodeOrNumeric(User::class, $request->input('user_id'))]);
        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $assignee = User::query()
            ->where('organization_id', $dsr->organization_id)
            ->findOrFail((int) $data['user_id']);
        $this->service->assign($dsr, $assignee, $request->user());

        return back()->with('status', __('Anfrage zugewiesen.'));
    }

    public function decide(Request $request, DataSubjectRequest $dsr): RedirectResponse {
        Gate::authorize('update', $dsr);
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:granted,partially,rejected'],
            'note' => ['required', 'string', 'min:5', 'max:20000'],
        ]);

        $this->service->decide($dsr, $data['decision'], $data['note'], $request->user());

        return redirect()->route('dataprotection.requests.show', $dsr)
            ->with('status', __('Entscheidung dokumentiert.'));
    }

    public function export(Request $request, DataSubjectRequest $dsr): Response {
        Gate::authorize('export', $dsr);

        $payload = $this->exporter->requestExport($dsr);
        $this->audit($request, 'privacy.dsr.exported', DataSubjectRequest::class, (int) $dsr->id);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response((string) $json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $dsr->request_number . '.json"',
        ]);
    }

    private function audit(Request $request, string $event, string $type, int $id): void {
        $org = $request->user()?->organization;
        AuditLog::create([
            'organization_id' => $org?->id,
            'user_id' => $request->user()?->id,
            'event' => $event,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'changes' => [],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
