<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RiskController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\{AssessmentKind, RiskCategory, RiskStatus, RiskTreatment};
use App\Http\Controllers\Controller;
use App\Http\Controllers\Isms\Concerns\StreamsRegisterExport;
use App\Models\Isms\{IsmsControl, IsmsRisk, IsmsRiskAssessment};
use App\Models\User;
use App\Services\Isms\{RegisterExportService, RiskService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ISMS-Risikoregister (Feature 044, MVP 1): Listenseite mit Filtern und
 * 5x5-Risikomatrix-Widget, Modal-CRUD, Statusübergänge. Autorisierung
 * über IsmsRiskPolicy (isms.viewAny/view/manage).
 *
 * Bewertungshistorie (Feature 046, Inkrement D): Brutto/Netto/Ziel-
 * Bewertungen je Risiko als unveränderliche, freigebbare Stände —
 * Erfassung/Freigabe/Entwurfs-Löschung über die assessment-Aktionen
 * (Pflege-Berechtigung = update am Risiko, Regeln im RiskService).
 */
class RiskController extends Controller {
    use StreamsRegisterExport;

    public function __construct(
        private readonly RiskService $service,
        private readonly RegisterExportService $exports,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsRisk::class);

        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'category' => (string) $request->query('category', 'all'),
            'treatment' => (string) $request->query('treatment', 'all'),
            'sort' => (string) $request->query('sort', 'score'),
        ];

        $query = IsmsRisk::query()->with([
            'owner',
            'controls',
            'assessments' => fn($q) => $q->orderByDesc('assessment_no'),
            'assessments.approvedBy',
        ]);

        if (RiskStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        if (RiskCategory::tryFrom($filters['category']) !== null) {
            $query->where('category', $filters['category']);
        }
        if (RiskTreatment::tryFrom($filters['treatment']) !== null) {
            $query->where('treatment', $filters['treatment']);
        }

        if ($filters['sort'] === 'newest') {
            $query->orderByDesc('created_at');
        } elseif ($filters['sort'] === 'review') {
            $query->orderByRaw('review_due_on is null')->orderBy('review_due_on');
        } else {
            $filters['sort'] = 'score';
            $query->orderByDesc('score')->orderBy('risk_no');
        }

        $hasActiveFilters = $filters['status'] !== 'all'
            || $filters['category'] !== 'all'
            || $filters['treatment'] !== 'all'
            || $filters['sort'] !== 'score';

        return view('isms.risks.index', [
            'risks' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'hasActiveFilters' => $hasActiveFilters,
            'matrix' => $this->service->matrix(),
            'reviewDueCount' => IsmsRisk::query()->reviewDue()->count(),
            'canManage' => Gate::allows('create', IsmsRisk::class),
        ]);
    }

    /**
     * Direkt-Export des Risikoregisters (?format=json|csv) — gleiches
     * Gate wie die Listenseite; meta-Block/Kopf trägt den Datenstand
     * (generated_at), „versioniert" leistet das Auditpaket.
     */
    public function export(Request $request): StreamedResponse {
        return $this->streamRegisterExport(
            $request,
            IsmsRisk::class,
            RegisterExportService::REGISTER_RISKS,
            fn(): array => $this->exports->riskRegister(),
        );
    }

    public function create(): View {
        Gate::authorize('create', IsmsRisk::class);

        return view('isms.risks._form_dialog', [
            'risk' => null,
            'controls' => $this->controlOptions(),
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsRisk::class);

        /** @var User $creator */
        $creator = Auth::user();
        $data = $this->validateRisk($request, $creator);

        $this->service->create($creator, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.risk_created'));
    }

    public function edit(IsmsRisk $risk): View {
        Gate::authorize('update', $risk);

        return view('isms.risks._form_dialog', [
            'risk' => $risk->load('controls'),
            'controls' => $this->controlOptions(),
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function update(Request $request, IsmsRisk $risk): RedirectResponse {
        Gate::authorize('update', $risk);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $this->validateRisk($request, $actor);

        $this->service->update($risk, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.risk_updated'));
    }

    /** Statusübergang entlang RiskStatus::allowedTransitions(). */
    public function transition(Request $request, IsmsRisk $risk): RedirectResponse {
        Gate::authorize('transition', $risk);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(RiskStatus::class)],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->transition($risk, RiskStatus::from($data['status']), $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.risk_transitioned'));
    }

    public function destroy(IsmsRisk $risk): RedirectResponse {
        Gate::authorize('delete', $risk);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($risk, $actor);

        return redirect()
            ->route('isms.risks.index')
            ->with('success', __('isms.flash.risk_deleted'));
    }

    // ── Bewertungshistorie (Feature 046, Inkrement D) ──────────────────────

    public function createAssessment(IsmsRisk $risk): View {
        Gate::authorize('update', $risk);

        return view('isms.risks._assessment_dialog', [
            'risk' => $risk,
        ]);
    }

    /** Legt IMMER einen neuen Entwurf an — kein Überschreiben alter Stände. */
    public function storeAssessment(Request $request, IsmsRisk $risk): RedirectResponse {
        Gate::authorize('update', $risk);

        $data = $request->validate([
            'kind' => ['required', 'string', Rule::enum(AssessmentKind::class)],
            'likelihood' => ['required', 'integer', 'between:1,5'],
            'impact' => ['required', 'integer', 'between:1,5'],
            'rationale' => ['nullable', 'string', 'max:10000'],
            'valid_until' => ['nullable', 'date'],
        ]);

        /** @var User $creator */
        $creator = Auth::user();
        $this->service->createAssessment($risk, $creator, AssessmentKind::from($data['kind']), $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.assessment_created'));
    }

    /** Freigabe (Person + Zeitpunkt); net-Freigabe synct das Risiko (RiskService). */
    public function approveAssessment(IsmsRiskAssessment $assessment): RedirectResponse {
        Gate::authorize('update', $assessment->risk()->firstOrFail());

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->approveAssessment($assessment, $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.assessment_approved'));
    }

    /** Entwurfs-Löschung — freigegebene Stände sind unlöschbar (RiskService). */
    public function destroyAssessment(IsmsRiskAssessment $assessment): RedirectResponse {
        Gate::authorize('update', $assessment->risk()->firstOrFail());

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->deleteAssessment($assessment, $actor);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.assessment_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRisk(Request $request, User $actor): array {
        // Sqid-Inputs vor der Validierung dekodieren (numerischer Fallback für Alt-Clients).
        if ($request->filled('owner_user_id')) {
            $request->merge(['owner_user_id' => Sqid::decodeOrNumeric(User::class, $request->input('owner_user_id'))]);
        }
        $controlIds = $request->input('control_ids');
        if (is_array($controlIds)) {
            $request->merge(['control_ids' => array_map(
                static fn($v) => $v === null || $v === '' ? null : Sqid::decodeOrNumeric(IsmsControl::class, $v),
                $controlIds,
            )]);
        }

        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', 'string', Rule::enum(RiskCategory::class)],
            'asset_ref' => ['nullable', 'string', 'max:180'],
            'threat' => ['nullable', 'string', 'max:10000'],
            'likelihood' => ['required', 'integer', 'between:1,5'],
            'impact' => ['required', 'integer', 'between:1,5'],
            'treatment' => ['required', 'string', Rule::enum(RiskTreatment::class)],
            'owner_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $actor->organization_id),
            ],
            'review_due_on' => ['nullable', 'date'],
            // Leerer Marker-Eintrag (Hidden-Feld im Dialog) ist erlaubt und
            // wird im RiskService herausgefiltert.
            'control_ids' => ['nullable', 'array'],
            'control_ids.*' => ['nullable', 'integer'],
        ]);
    }

    /**
     * Maßnahmen der Organisation für die Mehrfachauswahl im Dialog
     * (normneutral, alphabetisch nach Titel).
     *
     * @return \Illuminate\Support\Collection<int, IsmsControl>
     */
    private function controlOptions() {
        return IsmsControl::query()
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    /**
     * Mitglieder der Organisation als Risk-Owner-Auswahl.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function ownerOptions() {
        /** @var User $user */
        $user = Auth::user();

        return User::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
