<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierAssessmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\{IncidentSeverity, SupplierAssessmentStatus};
use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsScope, IsmsSupplierAssessment};
use App\Models\{Supplier, User};
use App\Services\Isms\SupplierAssessmentService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ISMS-Lieferantenbewertung (Feature 044, MVP 2/3 „Lieferanten und
 * Verträge"): Listenseite mit Filtern (Status/Kritikalität/Risiko), Modal-CRUD,
 * Statusübergängen und optionalem Bezug auf das Lieferanten-Stammdatenmodell.
 * Autorisierung über die IsmsSupplierAssessmentPolicy (isms.viewAny/view/manage).
 */
class SupplierAssessmentController extends Controller {
    public function __construct(
        private readonly SupplierAssessmentService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsSupplierAssessment::class);

        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'criticality' => (string) $request->query('criticality', 'all'),
            'risk' => (string) $request->query('risk', 'all'),
            'sort' => (string) $request->query('sort', 'criticality'),
        ];

        $query = IsmsSupplierAssessment::query()->with(['owner', 'supplier', 'scope']);

        if (SupplierAssessmentStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        if (IncidentSeverity::tryFrom($filters['criticality']) !== null) {
            $query->where('criticality', $filters['criticality']);
        }
        if (IncidentSeverity::tryFrom($filters['risk']) !== null) {
            $query->where('risk_rating', $filters['risk']);
        }

        if ($filters['sort'] === 'review') {
            $query->orderByRaw('next_review_on is null')->orderBy('next_review_on');
        } elseif ($filters['sort'] === 'risk') {
            $query->orderByRaw("FIELD(risk_rating, 'critical','high','medium','low')")->orderByDesc('assessment_no');
        } else {
            $filters['sort'] = 'criticality';
            $query->orderByRaw("FIELD(criticality, 'critical','high','medium','low')")->orderByDesc('assessment_no');
        }

        $hasActiveFilters = $filters['status'] !== 'all'
            || $filters['criticality'] !== 'all'
            || $filters['risk'] !== 'all'
            || $filters['sort'] !== 'criticality';

        return view('isms.suppliers.index', [
            'assessments' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'hasActiveFilters' => $hasActiveFilters,
            'overdueCount' => IsmsSupplierAssessment::query()->reviewOverdue()->count(),
            'flaggedCount' => IsmsSupplierAssessment::query()->where('status', SupplierAssessmentStatus::Flagged->value)->count(),
            'canManage' => Gate::allows('create', IsmsSupplierAssessment::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsSupplierAssessment::class);

        return view('isms.suppliers._form_dialog', [
            'assessment' => null,
            'suppliers' => $this->supplierOptions(),
            'scopes' => $this->scopeOptions(),
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsSupplierAssessment::class);

        /** @var User $creator */
        $creator = Auth::user();
        $this->service->create($creator, $this->validateAssessment($request, $creator));

        return redirect()->back()->with('success', __('isms.flash.supplier_created'));
    }

    public function edit(IsmsSupplierAssessment $supplier): View {
        Gate::authorize('update', $supplier);

        return view('isms.suppliers._form_dialog', [
            'assessment' => $supplier,
            'suppliers' => $this->supplierOptions(),
            'scopes' => $this->scopeOptions(),
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function update(Request $request, IsmsSupplierAssessment $supplier): RedirectResponse {
        Gate::authorize('update', $supplier);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->update($supplier, $actor, $this->validateAssessment($request, $actor));

        return redirect()->back()->with('success', __('isms.flash.supplier_updated'));
    }

    /** Statusübergang entlang SupplierAssessmentStatus::allowedTransitions(). */
    public function transition(Request $request, IsmsSupplierAssessment $supplier): RedirectResponse {
        Gate::authorize('transition', $supplier);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(SupplierAssessmentStatus::class)],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->transition($supplier, SupplierAssessmentStatus::from($data['status']), $actor);

        return redirect()->back()->with('success', __('isms.flash.supplier_transitioned'));
    }

    public function destroy(IsmsSupplierAssessment $supplier): RedirectResponse {
        Gate::authorize('delete', $supplier);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($supplier, $actor);

        return redirect()->route('isms.suppliers.index')->with('success', __('isms.flash.supplier_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAssessment(Request $request, User $actor): array {
        $data = $request->validate([
            'supplier_id' => [
                'nullable', 'integer',
                Rule::exists('suppliers', 'id')->where('organization_id', $actor->organization_id),
            ],
            // Freitext-Name ist Pflicht, wenn kein Stammdaten-Lieferant gewählt ist.
            'supplier_name' => ['nullable', 'required_without:supplier_id', 'string', 'max:250'],
            'criticality' => ['nullable', 'string', Rule::enum(IncidentSeverity::class)],
            'service_description' => ['nullable', 'string', 'max:10000'],
            'isms_scope_id' => [
                'nullable', 'integer',
                Rule::exists('isms_scopes', 'id')->where('organization_id', $actor->organization_id),
            ],
            'security_requirements' => ['nullable', 'string', 'max:10000'],
            'has_nda' => ['nullable', 'boolean'],
            'has_dpa' => ['nullable', 'boolean'],
            'dpa_ref' => ['nullable', 'string', 'max:250'],
            'audit_right' => ['nullable', 'boolean'],
            'last_review_on' => ['nullable', 'date'],
            'next_review_on' => ['nullable', 'date'],
            'risk_rating' => ['nullable', 'string', Rule::enum(IncidentSeverity::class)],
            'findings' => ['nullable', 'string', 'max:10000'],
            'owner_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $actor->organization_id),
            ],
        ]);

        // Unchecked Checkboxen liefern keinen Key — explizit auf false setzen.
        foreach (['has_nda', 'has_dpa', 'audit_right'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }

    /** @return \Illuminate\Support\Collection<int, Supplier> */
    private function supplierOptions() {
        return Supplier::query()->orderBy('name')->get(['id', 'name', 'number']);
    }

    /** @return \Illuminate\Support\Collection<int, IsmsScope> */
    private function scopeOptions() {
        return IsmsScope::query()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']);
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function ownerOptions() {
        /** @var User $user */
        $user = Auth::user();

        return User::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
