<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Investments;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase, InvestmentDeviation, InvestmentOption};
use App\Models\{Supplier, User};
use App\Services\Investments\InvestmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Investitionsakten (Feature 069, MVP-200–207): Liste, Akte mit
 * Variantenvergleich, Budgetantrag + Freigabekette, Verknüpfungen,
 * Ist-Werten, Abweichungen und Nachbewertung.
 */
class InvestmentController extends Controller {
    public function __construct(private readonly InvestmentService $investments) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', InvestmentCase::class);

        $status = $request->string('status')->toString();
        $statusFilter = in_array($status, InvestmentCase::STATUSES, true) ? $status : '';
        $category = $request->string('category')->toString();
        $categoryFilter = in_array($category, InvestmentCase::CATEGORIES, true) ? $category : '';

        return view('investments.index', [
            'cases' => InvestmentCase::query()
                ->with(['responsible', 'costCenter'])
                ->when($statusFilter !== '', fn($q) => $q->where('status', $statusFilter))
                ->when($categoryFilter !== '', fn($q) => $q->where('category', $categoryFilter))
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'statuses' => InvestmentCase::STATUSES,
            'categories' => InvestmentCase::CATEGORIES,
            'filters' => ['status' => $statusFilter, 'category' => $categoryFilter],
        ]);
    }

    public function create(): View {
        Gate::authorize('create', InvestmentCase::class);

        return $this->formView(new InvestmentCase());
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', InvestmentCase::class);

        /** @var User $actor */
        $actor = Auth::user();
        $case = InvestmentCase::query()->create([
            ...$this->validated($request),
            'organization_id' => (int) $actor->organization_id,
            'created_by' => $actor->id,
        ]);
        $case->audit('investment.created', ['title' => $case->title]);

        return redirect()->route('investments.show', $case)->with('status', __('Investitionsakte angelegt.'));
    }

    public function show(InvestmentCase $case): View {
        Gate::authorize('view', $case);
        $case->load(['options.supplier', 'budgetRequests.approvals', 'links.linkable', 'actuals', 'deviations', 'review', 'responsible', 'costCenter', 'project']);

        return view('investments.show', [
            'case' => $case,
            'projection' => $this->investments->projection($case),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name', 'company']),
            'hasCostCenters' => CostCenter::query()->where('active', true)->exists(),
        ]);
    }

    public function edit(InvestmentCase $case): View {
        Gate::authorize('update', $case);

        return $this->formView($case);
    }

    public function update(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $case->update($this->validated($request));

        return redirect()->route('investments.show', $case)->with('status', __('Akte aktualisiert.'));
    }

    public function destroy(InvestmentCase $case): RedirectResponse {
        Gate::authorize('delete', $case);
        $case->options()->delete();
        $case->delete();

        return redirect()->route('investments.index')->with('status', __('Akte gelöscht.'));
    }

    public function updateStatus(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validate([
            // Freigabe-/Abschluss-Status laufen NUR über Service-Aktionen.
            'status' => ['required', 'in:idea,screening,comparison,budget_request,in_progress,completed,deferred'],
        ]);

        // Umsetzung/Abschluss erst nach genehmigtem Budget.
        if (in_array($data['status'], ['in_progress', 'completed'], true) && $case->approvedBudget() === null) {
            return back()->with('error', __('Umsetzung erst nach genehmigtem Budget.'));
        }
        $case->update(['status' => $data['status']]);

        return back()->with('status', __('Status aktualisiert.'));
    }

    // ── Kostenstellen (D2, Blocked-State-Auflösung) ──────────────────────

    public function storeCostCenter(Request $request): RedirectResponse {
        Gate::authorize('create', InvestmentCase::class);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30'],
            'label' => ['required', 'string', 'max:200'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        CostCenter::query()->firstOrCreate(
            ['organization_id' => (int) $actor->organization_id, 'code' => $data['code']],
            ['label' => $data['label'], 'active' => true],
        );

        return back()->with('status', __('Kostenstelle angelegt.'));
    }

    // ── Variantenvergleich (MVP-201) ─────────────────────────────────────

    public function addOption(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $request->merge(['supplier_id' => \App\Support\Sqid::decodeOrNumeric(Supplier::class, $request->input('supplier_id'))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'supplier_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('suppliers')],
            'one_time_cost' => ['required', 'numeric', 'min:0'],
            'recurring_cost_yearly' => ['nullable', 'numeric', 'min:0'],
            'delivery_weeks' => ['nullable', 'integer', 'min:0', 'max:520'],
            'useful_life_years' => ['nullable', 'integer', 'min:0', 'max:99'],
            'quality_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'risk_note' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $case->options()->create([
            'organization_id' => $case->organization_id,
            ...$data,
            'recurring_cost_yearly' => $data['recurring_cost_yearly'] ?? 0,
        ]);
        if ($case->status === 'idea' || $case->status === 'screening') {
            $case->update(['status' => 'comparison']);
        }

        return back()->with('status', __('Variante erfasst.'));
    }

    public function recommendOption(InvestmentCase $case, InvestmentOption $option): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($option->investment_case_id === $case->id, 404);

        $case->options()->update(['recommended' => false]);
        $option->update(['recommended' => true]);
        $case->audit('investment.option_recommended', ['option' => $option->title]);

        return back()->with('status', __('Empfehlung gesetzt.'));
    }

    public function removeOption(InvestmentCase $case, InvestmentOption $option): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($option->investment_case_id === $case->id, 404);
        $option->delete();

        return back()->with('status', __('Variante entfernt.'));
    }

    // ── Budgetantrag + Freigabe (MVP-202/203) ────────────────────────────

    public function submitBudget(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $request->merge(['cost_center_id' => \App\Support\Sqid::decodeOrNumeric(CostCenter::class, $request->input('cost_center_id'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'cost_kind' => ['required', 'in:purchase,leasing,service,mixed'],
            'financing' => ['required', 'in:cash,loan,leasing,subsidy,mixed'],
            'cost_center_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('cost_centers')],
            'payment_plan' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($data['cost_center_id'])) {
            $case->update(['cost_center_id' => $data['cost_center_id']]);
        }

        try {
            $this->investments->submitBudget($case->refresh(), $data, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Budgetantrag eingereicht — Freigabekette gestartet.'));
    }

    public function approveBudget(Request $request, InvestmentCase $case, InvestmentBudgetRequest $budgetRequest): RedirectResponse {
        Gate::authorize('approve', $case);
        abort_unless($budgetRequest->investment_case_id === $case->id, 404);

        try {
            $result = $this->investments->approveBudget($budgetRequest, $this->actor(), $request->input('reason'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $result === 'approved_all'
            ? __('Budget genehmigt und eingefroren.')
            : __('Freigabestufe erteilt — weitere Stufe offen (Vier-Augen).'));
    }

    public function rejectBudget(Request $request, InvestmentCase $case, InvestmentBudgetRequest $budgetRequest): RedirectResponse {
        Gate::authorize('approve', $case);
        abort_unless($budgetRequest->investment_case_id === $case->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->investments->rejectBudget($budgetRequest, $this->actor(), $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Budgetantrag abgelehnt.'));
    }

    // ── Verknüpfungen + Ist-Werte (MVP-204/205) ──────────────────────────

    public function addLink(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        if ($case->approvedBudget() === null) {
            return back()->with('error', __('Folgeobjekte werden erst nach der Freigabe verknüpft.'));
        }

        $data = $request->validate([
            'linkable_type' => ['required', 'in:project,purchase_order,asset,incoming_einvoice,document'],
            'linkable_sqid' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $map = [
            'project' => \App\Models\Project::class,
            'purchase_order' => \App\Models\PurchaseOrder::class,
            'asset' => \App\Models\Asset::class,
            'incoming_einvoice' => \App\Models\IncomingEInvoice::class,
            'document' => \App\Models\Document::class,
        ];
        $class = $map[$data['linkable_type']];
        $id = \App\Support\Sqid::decodeOrNumeric($class, $data['linkable_sqid']);
        $target = $id !== null ? $class::query()->whereKey($id)->first() : null;
        if ($target === null) {
            return back()->with('error', __('Zielobjekt nicht gefunden.'));
        }

        $case->links()->firstOrCreate([
            'organization_id' => $case->organization_id,
            'linkable_type' => $target->getMorphClass(),
            'linkable_id' => $target->getKey(),
        ], [
            'note' => $data['note'] ?? null,
            'created_by' => (int) Auth::id(),
        ]);
        $case->audit('investment.linked', ['type' => $data['linkable_type'], 'id' => $target->getKey()]);
        if ($case->status === 'approved') {
            $case->update(['status' => 'in_progress']);
        }

        return back()->with('status', __('Verknüpfung angelegt.'));
    }

    public function addActual(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:-999999999', 'max:999999999'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $case->actuals()->create([
            'organization_id' => $case->organization_id,
            'source' => 'manual',
            'amount' => (string) $data['amount'],
            'occurred_on' => $data['occurred_on'],
            'note' => $data['note'] ?? null,
            'created_by' => (int) Auth::id(),
        ]);

        return back()->with('status', __('Ist-Wert erfasst.'));
    }

    // ── Abweichungen + Nachtrag (MVP-206) ────────────────────────────────

    public function addDeviation(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $data = $request->validate([
            'kind' => ['required', 'in:budget,schedule,scope,cancellation'],
            'description' => ['required', 'string', 'max:1000'],
            'amount_delta' => ['nullable', 'numeric'],
        ]);

        $case->deviations()->create([
            'organization_id' => $case->organization_id,
            'kind' => $data['kind'],
            'description' => $data['description'],
            'amount_delta' => $data['amount_delta'] ?? null,
            'status' => 'open',
            'created_by' => (int) Auth::id(),
        ]);

        return back()->with('status', __('Abweichung dokumentiert.'));
    }

    public function decideDeviation(Request $request, InvestmentCase $case, InvestmentDeviation $deviation): RedirectResponse {
        Gate::authorize('approve', $case);
        abort_unless($deviation->investment_case_id === $case->id, 404);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->investments->decideDeviation($deviation, $data['decision'], $data['note'] ?? null, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Abweichung entschieden.'));
    }

    public function supplementBudget(Request $request, InvestmentCase $case, InvestmentDeviation $deviation): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($deviation->investment_case_id === $case->id, 404);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->investments->supplementBudget($case, $deviation, $data, $this->actor());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('Nachtrag eingereicht — Freigabekette gestartet.'));
    }

    // ── Nachbewertung (MVP-207) ──────────────────────────────────────────

    public function storeReview(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        if (! in_array($case->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', __('Nachbewertung erst nach Abschluss oder Abbruch.'));
        }
        if ($case->review()->exists()) {
            return back()->with('error', __('Es existiert bereits eine Nachbewertung.'));
        }

        $data = $request->validate([
            'benefit_result' => ['required', 'string', 'max:5000'],
            'economics_result' => ['nullable', 'string', 'max:5000'],
            'lessons' => ['nullable', 'string', 'max:5000'],
            'follow_up' => ['nullable', 'string', 'max:5000'],
        ]);

        $case->review()->create([
            'organization_id' => $case->organization_id,
            ...$data,
            'reviewed_by' => (int) Auth::id(),
            'reviewed_at' => now(),
        ]);
        $case->update(['status' => 'post_review']);
        $case->audit('investment.reviewed', []);

        return back()->with('status', __('Nachbewertung dokumentiert.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $request->merge([
            'responsible_user_id' => \App\Support\Sqid::decodeOrNumeric(User::class, $request->input('responsible_user_id')),
            'cost_center_id' => \App\Support\Sqid::decodeOrNumeric(CostCenter::class, $request->input('cost_center_id')),
        ]);

        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'in:' . implode(',', InvestmentCase::CATEGORIES)],
            'reason' => ['nullable', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', 'max:5000'],
            'urgency' => ['required', 'in:low,medium,high'],
            'risk_note' => ['nullable', 'string', 'max:5000'],
            'responsible_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('users')],
            'cost_center_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('cost_centers')],
            'cost_center_label' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
    }

    private function formView(InvestmentCase $case): View {
        return view('investments._form_dialog', [
            'case' => $case,
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'costCenters' => CostCenter::query()->where('active', true)->orderBy('code')->get(),
        ]);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
