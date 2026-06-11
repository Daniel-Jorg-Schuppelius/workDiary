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

use App\Enums\Isms\{RiskCategory, RiskStatus, RiskTreatment};
use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsControl, IsmsRisk};
use App\Models\User;
use App\Services\Isms\RiskService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ISMS-Risikoregister (Feature 044, MVP 1): Listenseite mit Filtern und
 * 5x5-Risikomatrix-Widget, Modal-CRUD, Statusübergänge. Autorisierung
 * über IsmsRiskPolicy (isms.viewAny/view/manage).
 */
class RiskController extends Controller {
    public function __construct(
        private readonly RiskService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsRisk::class);

        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'category' => (string) $request->query('category', 'all'),
            'treatment' => (string) $request->query('treatment', 'all'),
            'sort' => (string) $request->query('sort', 'score'),
        ];

        $query = IsmsRisk::query()->with(['owner', 'controls']);

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

    /**
     * @return array<string, mixed>
     */
    private function validateRisk(Request $request, User $actor): array {
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
     * Controls der Organisation für die Mehrfachauswahl im Dialog
     * (natürlich nach Code sortiert: A.5.2 vor A.5.10).
     *
     * @return \Illuminate\Support\Collection<int, IsmsControl>
     */
    private function controlOptions() {
        return IsmsControl::query()
            ->get(['id', 'code', 'title'])
            ->sortBy('code', SORT_NATURAL)
            ->values();
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
