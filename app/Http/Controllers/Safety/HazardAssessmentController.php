<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HazardAssessmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Safety;

use App\Enums\Safety\HazardAssessmentStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Safety\{HazardAssessment, HazardAssessmentItem};
use App\Models\User;
use App\Services\Safety\HazardAssessmentService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Register der Gefährdungsbeurteilungen (Feature 132): Voll-Höhe-Liste,
 * Detailseite mit Positionen (Modal-Dialoge), Statusmaschine und
 * Folgeversion. Fachlogik im HazardAssessmentService.
 */
class HazardAssessmentController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly HazardAssessmentService $service,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', HazardAssessment::class);

        $status = (string) $request->query('status', '');
        $onlyCurrent = $request->query('current', '1') !== '0';

        $query = HazardAssessment::query()
            ->with('approvedBy:id,name')
            ->withCount('items')
            ->orderByDesc('assessment_no')
            ->orderByDesc('version');

        if (HazardAssessmentStatus::tryFrom($status) instanceof HazardAssessmentStatus) {
            $query->where('status', $status);
        } elseif ($onlyCurrent) {
            // Standard: nur aktuelle Stände — archivierte (abgelöste) Versionen ausblenden.
            $query->where('status', '!=', HazardAssessmentStatus::Archived->value);
        }

        return view('safety.assessments.index', [
            'assessments' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'onlyCurrent' => $onlyCurrent && $status === '',
            'reviewDueCount' => HazardAssessment::query()->reviewDue()->count(),
            'canManage' => Gate::allows('create', HazardAssessment::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', HazardAssessment::class);

        return view('safety.assessments._form_dialog', ['assessment' => null]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', HazardAssessment::class);

        /** @var User $actor */
        $actor = Auth::user();
        $assessment = $this->service->create($this->currentOrganization(), $actor, $this->validateHeader($request));

        return redirect()
            ->route('safety.assessments.show', $assessment)
            ->with('success', __('safety.register.flash.assessment_created'));
    }

    public function show(HazardAssessment $assessment): View {
        Gate::authorize('view', $assessment);

        $assessment->load(['items', 'approvedBy:id,name', 'createdBy:id,name', 'supersedes', 'successors']);

        return view('safety.assessments.show', [
            'assessment' => $assessment,
            'canManage' => Gate::allows('update', $assessment),
        ]);
    }

    public function edit(HazardAssessment $assessment): View {
        Gate::authorize('update', $assessment);

        return view('safety.assessments._form_dialog', ['assessment' => $assessment]);
    }

    public function update(Request $request, HazardAssessment $assessment): RedirectResponse {
        Gate::authorize('update', $assessment);

        $this->service->update($assessment, $this->validateHeader($request));

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.assessment_updated'));
    }

    /** Statusübergang entlang HazardAssessmentStatus::allowedTransitions(). */
    public function transition(Request $request, HazardAssessment $assessment): RedirectResponse {
        Gate::authorize('transition', $assessment);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(HazardAssessmentStatus::class)],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->transition($assessment, HazardAssessmentStatus::from($data['status']), $actor);

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.assessment_transitioned'));
    }

    /** Folgeversion eines freigegebenen Standes (Original wird archiviert). */
    public function newVersion(HazardAssessment $assessment): RedirectResponse {
        Gate::authorize('update', $assessment);

        /** @var User $actor */
        $actor = Auth::user();
        $copy = $this->service->newVersion($assessment, $actor);

        return redirect()
            ->route('safety.assessments.show', $copy)
            ->with('success', __('safety.register.flash.assessment_version_created', ['version' => $copy->version]));
    }

    public function destroy(HazardAssessment $assessment): RedirectResponse {
        Gate::authorize('delete', $assessment);

        $this->service->delete($assessment);

        return redirect()
            ->route('safety.assessments.index')
            ->with('success', __('safety.register.flash.assessment_deleted'));
    }

    // ── Positionen ─────────────────────────────────────────────────────────

    public function createItem(HazardAssessment $assessment): View {
        Gate::authorize('update', $assessment);

        return view('safety.assessments._item_dialog', ['assessment' => $assessment, 'item' => null]);
    }

    public function storeItem(Request $request, HazardAssessment $assessment): RedirectResponse {
        Gate::authorize('update', $assessment);

        $this->service->addItem($assessment, $this->validateItem($request));

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.item_created'));
    }

    public function editItem(HazardAssessment $assessment, HazardAssessmentItem $item): View {
        Gate::authorize('update', $assessment);
        $this->assertBelongs($assessment, $item);

        return view('safety.assessments._item_dialog', ['assessment' => $assessment, 'item' => $item]);
    }

    public function updateItem(Request $request, HazardAssessment $assessment, HazardAssessmentItem $item): RedirectResponse {
        Gate::authorize('update', $assessment);
        $this->assertBelongs($assessment, $item);

        $this->service->updateItem($item, $this->validateItem($request));

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.item_updated'));
    }

    public function destroyItem(HazardAssessment $assessment, HazardAssessmentItem $item): RedirectResponse {
        Gate::authorize('update', $assessment);
        $this->assertBelongs($assessment, $item);

        $this->service->removeItem($item);

        return redirect()
            ->back()
            ->with('success', __('safety.register.flash.item_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateHeader(Request $request): array {
        return $request->validate([
            'area' => ['required', 'string', 'min:2', 'max:180'],
            'activity' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'review_due_on' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request): array {
        return $request->validate([
            'hazard' => ['required', 'string', 'min:2', 'max:255'],
            'measure' => ['nullable', 'string', 'max:10000'],
            'severity_before' => ['required', 'integer', 'between:1,5'],
            'likelihood_before' => ['required', 'integer', 'between:1,5'],
            'severity_after' => ['nullable', 'integer', 'between:1,5'],
            'likelihood_after' => ['nullable', 'integer', 'between:1,5'],
        ]);
    }

    private function assertBelongs(HazardAssessment $assessment, HazardAssessmentItem $item): void {
        abort_unless((int) $item->hazard_assessment_id === (int) $assessment->id, 404);
    }
}
