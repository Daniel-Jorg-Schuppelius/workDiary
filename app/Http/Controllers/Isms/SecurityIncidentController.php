<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityIncidentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\{IncidentSeverity, SecurityIncidentCategory, SecurityIncidentStatus};
use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsControl, IsmsRisk, IsmsSecurityIncident};
use App\Models\User;
use App\Services\Isms\SecurityIncidentService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ISMS-Sicherheitsvorfälle (Feature 044, MVP 2): Listenseite mit Filtern
 * (Status/Severity/Kategorie), Modal-CRUD, Statusübergänge mit Pflichtfeldern
 * (closed erfordert Ursache + Lessons Learned, SecurityIncidentService) und
 * Aufklappbereich mit verknüpften Risiken/Maßnahmen. Autorisierung über die
 * IsmsSecurityIncidentPolicy (isms.viewAny/view/manage).
 */
class SecurityIncidentController extends Controller {
    public function __construct(
        private readonly SecurityIncidentService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsSecurityIncident::class);

        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'severity' => (string) $request->query('severity', 'all'),
            'category' => (string) $request->query('category', 'all'),
        ];

        $query = IsmsSecurityIncident::query()->with(['owner', 'reporter', 'risks', 'controls']);

        if (SecurityIncidentStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        }
        if (IncidentSeverity::tryFrom($filters['severity']) !== null) {
            $query->where('severity', $filters['severity']);
        }
        if (SecurityIncidentCategory::tryFrom($filters['category']) !== null) {
            $query->where('category', $filters['category']);
        }

        $hasActiveFilters = $filters['status'] !== 'all'
            || $filters['severity'] !== 'all'
            || $filters['category'] !== 'all';

        return view('isms.incidents.index', [
            'incidents' => $query->orderByDesc('detected_at')->orderByDesc('incident_no')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'hasActiveFilters' => $hasActiveFilters,
            'openCriticalCount' => IsmsSecurityIncident::query()->openCritical()->count(),
            'openCount' => IsmsSecurityIncident::query()->open()->count(),
            'canManage' => Gate::allows('create', IsmsSecurityIncident::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsSecurityIncident::class);

        return view('isms.incidents._form_dialog', [
            'incident' => null,
            'risks' => $this->riskOptions(),
            'controls' => $this->controlOptions(),
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsSecurityIncident::class);

        /** @var User $creator */
        $creator = Auth::user();
        $this->service->create($creator, $this->validateIncident($request, $creator));

        return redirect()->back()->with('success', __('isms.flash.incident_created'));
    }

    public function edit(IsmsSecurityIncident $incident): View {
        Gate::authorize('update', $incident);

        return view('isms.incidents._form_dialog', [
            'incident' => $incident->load(['risks', 'controls']),
            'risks' => $this->riskOptions(),
            'controls' => $this->controlOptions(),
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function update(Request $request, IsmsSecurityIncident $incident): RedirectResponse {
        Gate::authorize('update', $incident);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->update($incident, $actor, $this->validateIncident($request, $actor));

        return redirect()->back()->with('success', __('isms.flash.incident_updated'));
    }

    /** Statusübergang entlang SecurityIncidentStatus::allowedTransitions(). */
    public function transition(Request $request, IsmsSecurityIncident $incident): RedirectResponse {
        Gate::authorize('transition', $incident);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(SecurityIncidentStatus::class)],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->transition($incident, SecurityIncidentStatus::from($data['status']), $actor);

        return redirect()->back()->with('success', __('isms.flash.incident_transitioned'));
    }

    public function destroy(IsmsSecurityIncident $incident): RedirectResponse {
        Gate::authorize('delete', $incident);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($incident, $actor);

        return redirect()->route('isms.incidents.index')->with('success', __('isms.flash.incident_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIncident(Request $request, User $actor): array {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', 'string', Rule::enum(SecurityIncidentCategory::class)],
            'severity' => ['required', 'string', Rule::enum(IncidentSeverity::class)],
            'detected_at' => ['nullable', 'date'],
            'occurred_at' => ['nullable', 'date'],
            'owner_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $actor->organization_id),
            ],
            'impact' => ['nullable', 'string', 'max:10000'],
            'root_cause' => ['nullable', 'string', 'max:10000'],
            'lessons_learned' => ['nullable', 'string', 'max:10000'],
            'personal_data_affected' => ['nullable', 'boolean'],
            'privacy_incident_ref' => ['nullable', 'string', 'max:64'],
            'risk_ids' => ['nullable', 'array'],
            'risk_ids.*' => ['nullable', 'integer'],
            'control_ids' => ['nullable', 'array'],
            'control_ids.*' => ['nullable', 'integer'],
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, IsmsRisk> */
    private function riskOptions() {
        return IsmsRisk::query()->orderBy('title')->get(['id', 'title']);
    }

    /** @return \Illuminate\Support\Collection<int, IsmsControl> */
    private function controlOptions() {
        return IsmsControl::query()->orderBy('title')->get(['id', 'title']);
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
