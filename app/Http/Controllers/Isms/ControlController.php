<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ControlController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Isms;

use App\Enums\Isms\ControlImplementationStatus;
use App\Http\Controllers\Controller;
use App\Models\Isms\{IsmsControl, IsmsRequirement};
use App\Models\User;
use App\Services\Isms\ControlService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * NORMNEUTRALE Maßnahmen (Feature 046; vormals Maßnahmenkatalog + SoA aus
 * Feature 044): Listenseite mit Filtern, Modal-CRUD (Titel, Status, Owner,
 * Anforderungs-Mehrfachauswahl, Nachweis-Notiz). Der Annex-A-Katalog-Import
 * lebt jetzt auf der Anforderungen-Seite (RequirementController).
 * Autorisierung über IsmsControlPolicy (isms.viewAny/view/manage).
 */
class ControlController extends Controller {
    public function __construct(
        private readonly ControlService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsControl::class);

        $filters = [
            'implementation_status' => (string) $request->query('implementation_status', 'all'),
        ];

        $query = IsmsControl::query()
            ->with(['owner', 'requirements'])
            ->withCount('risks');

        if (ControlImplementationStatus::tryFrom($filters['implementation_status']) !== null) {
            $query->where('implementation_status', $filters['implementation_status']);
        }

        $hasActiveFilters = $filters['implementation_status'] !== 'all';

        return view('isms.controls.index', [
            'controls' => $query->orderBy('title')->get(),
            'filters' => $filters,
            'hasActiveFilters' => $hasActiveFilters,
            'canManage' => Gate::allows('create', IsmsControl::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsControl::class);

        return view('isms.controls._form_dialog', [
            'control' => null,
            'requirements' => $this->requirementOptions(),
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsControl::class);

        /** @var User $creator */
        $creator = Auth::user();
        $data = $this->validateControl($request, $creator);

        $this->service->create($creator, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.control_created'));
    }

    public function edit(IsmsControl $control): View {
        Gate::authorize('update', $control);

        return view('isms.controls._form_dialog', [
            'control' => $control->load('requirements'),
            'requirements' => $this->requirementOptions(),
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function update(Request $request, IsmsControl $control): RedirectResponse {
        Gate::authorize('update', $control);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $this->validateControl($request, $actor);

        $this->service->update($control, $actor, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.control_updated'));
    }

    public function destroy(IsmsControl $control): RedirectResponse {
        Gate::authorize('delete', $control);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($control, $actor);

        return redirect()
            ->route('isms.controls.index')
            ->with('success', __('isms.flash.control_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateControl(Request $request, User $actor): array {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'implementation_status' => ['required', 'string', Rule::enum(ControlImplementationStatus::class)],
            'evidence_note' => ['nullable', 'string', 'max:10000'],
            'owner_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $actor->organization_id),
            ],
            // Leerer Marker-Eintrag (Hidden-Feld im Dialog) ist erlaubt und
            // wird im ControlService herausgefiltert; org-sicher aufgelöst
            // in ControlService::syncRequirements().
            'requirement_ids' => ['nullable', 'array'],
            'requirement_ids.*' => ['nullable', 'integer'],
        ]);
    }

    /**
     * Anforderungen der Organisation für die Mehrfachauswahl im Dialog
     * (Norm, dann Ref-Nr. natürlich sortiert: A.5.2 vor A.5.10).
     *
     * @return \Illuminate\Support\Collection<int, IsmsRequirement>
     */
    private function requirementOptions() {
        return IsmsRequirement::query()
            ->get(['id', 'norm', 'edition', 'ref_no', 'title'])
            ->sort(fn(IsmsRequirement $a, IsmsRequirement $b): int => strcmp($a->norm, $b->norm)
                ?: strnatcmp($a->ref_no, $b->ref_no))
            ->values();
    }

    /**
     * Mitglieder der Organisation als Maßnahmen-Owner-Auswahl.
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
