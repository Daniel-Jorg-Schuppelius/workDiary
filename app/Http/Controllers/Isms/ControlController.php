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

use App\Enums\Isms\{ControlImplementationStatus, ControlSource};
use App\Http\Controllers\Controller;
use App\Models\Isms\IsmsControl;
use App\Models\User;
use App\Services\Isms\ControlService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ISMS-Maßnahmenkatalog (Feature 044, MVP 1): Listenseite mit Filtern,
 * Modal-CRUD (SoA-Felder), idempotenter Annex-A-Katalog-Import.
 * Autorisierung über IsmsControlPolicy (isms.viewAny/view/manage).
 */
class ControlController extends Controller {
    public function __construct(
        private readonly ControlService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IsmsControl::class);

        $filters = [
            'source' => (string) $request->query('source', 'all'),
            'applicable' => (string) $request->query('applicable', 'all'),
            'implementation_status' => (string) $request->query('implementation_status', 'all'),
        ];

        $query = IsmsControl::query()->with('owner')->withCount('risks');

        if (ControlSource::tryFrom($filters['source']) !== null) {
            $query->where('source', $filters['source']);
        }
        if (in_array($filters['applicable'], ['yes', 'no'], true)) {
            $query->where('applicable', $filters['applicable'] === 'yes');
        }
        if (ControlImplementationStatus::tryFrom($filters['implementation_status']) !== null) {
            $query->where('implementation_status', $filters['implementation_status']);
        }

        // 93 Katalog-Controls + eigene Maßnahmen: bewusst ohne Pagination,
        // natürliche Code-Sortierung (A.5.2 vor A.5.10) im PHP-Nachgang.
        $controls = $query->get()->sortBy('code', SORT_NATURAL)->values();

        $hasActiveFilters = $filters['source'] !== 'all'
            || $filters['applicable'] !== 'all'
            || $filters['implementation_status'] !== 'all';

        return view('isms.controls.index', [
            'controls' => $controls,
            'filters' => $filters,
            'hasActiveFilters' => $hasActiveFilters,
            'catalogLoaded' => IsmsControl::query()->where('source', ControlSource::Iso27001AnnexA->value)->exists(),
            'canManage' => Gate::allows('create', IsmsControl::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IsmsControl::class);

        return view('isms.controls._form_dialog', [
            'control' => null,
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', IsmsControl::class);

        /** @var User $creator */
        $creator = Auth::user();
        $data = $this->validateControl($request, $creator, null);

        $this->service->create($creator, $data);

        return redirect()
            ->back()
            ->with('success', __('isms.flash.control_created'));
    }

    public function edit(IsmsControl $control): View {
        Gate::authorize('update', $control);

        return view('isms.controls._form_dialog', [
            'control' => $control,
            'owners' => $this->ownerOptions(),
        ]);
    }

    public function update(Request $request, IsmsControl $control): RedirectResponse {
        Gate::authorize('update', $control);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $this->validateControl($request, $actor, $control);

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

    /** Annex-A-Referenzkatalog laden (idempotent, nur Code + Kurztitel). */
    public function import(): RedirectResponse {
        Gate::authorize('import', IsmsControl::class);

        /** @var User $actor */
        $actor = Auth::user();
        $created = $this->service->importAnnexCatalog($actor);

        return redirect()
            ->route('isms.controls.index')
            ->with('success', __('isms.flash.catalog_imported', ['count' => $created]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateControl(Request $request, User $actor, ?IsmsControl $control): array {
        $isCatalog = $control !== null && $control->source === ControlSource::Iso27001AnnexA;

        return $request->validate([
            // Code/Titel sind bei Annex-A-Controls Referenz (Code unveränderlich,
            // erzwungen im ControlService); bei eigenen Maßnahmen Pflicht.
            'code' => [
                $isCatalog ? 'nullable' : 'required', 'string', 'max:24',
                Rule::unique('isms_controls', 'code')
                    ->where('organization_id', $actor->organization_id)
                    ->ignore($control?->id),
            ],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'applicable' => ['nullable', 'boolean'],
            // SoA-Regel: Begründung Pflicht bei Nicht-Anwendbarkeit —
            // zusätzlich zentral im ControlService durchgesetzt.
            'justification' => ['nullable', 'string', 'max:5000', 'required_if:applicable,0'],
            'implementation_status' => ['required', 'string', Rule::enum(ControlImplementationStatus::class)],
            'evidence_note' => ['nullable', 'string', 'max:10000'],
            'owner_user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $actor->organization_id),
            ],
        ]);
    }

    /**
     * Mitglieder der Organisation als Control-Owner-Auswahl.
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
