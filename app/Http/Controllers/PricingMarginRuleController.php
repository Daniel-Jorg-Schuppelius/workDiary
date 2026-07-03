<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PricingMarginRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Procurement\PriceRounding;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\SavePricingMarginRuleRequest;
use App\Models\{PriceChangeRequest, PricingMarginRule, Supplier, User};
use App\Services\Procurement\PriceApprovalService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Margenregeln für Verkaufspreisvorschläge (Feature 050, MVP-095). Modul-Gating
 * über `pricing-margin-rules.*` → module.lager; lesen mit inventory.viewAny,
 * verwalten mit inventory.configure. Verwaltet zusätzlich den Freigabemodus
 * für Preisübernahmen und die offenen Vier-Augen-Anträge.
 */
class PricingMarginRuleController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        $this->canView();

        return view('pricing-margin-rules.index', [
            'rules' => PricingMarginRule::query()->with('supplier')->orderByDesc('priority')->orderBy('name')->paginate(50),
            'approvalMode' => $this->currentOrganization()->pricingApprovalMode(),
            'openApprovals' => PriceChangeRequest::query()->where('status', PriceChangeRequest::STATUS_REQUESTED)->count(),
        ]);
    }

    /** Offene und entschiedene Preisfreigabe-Anträge (Vier-Augen, MVP-095). */
    public function approvals(): View {
        $this->canView();

        return view('pricing-margin-rules.approvals', [
            'requests' => PriceChangeRequest::query()
                ->with(['article:id,name', 'item:id,name,external_no', 'requestedBy:id,name', 'decidedBy:id,name'])
                ->orderByRaw("CASE WHEN status = 'requested' THEN 0 ELSE 1 END")
                ->latest()
                ->paginate(50),
            'canDecide' => Auth::user()?->can(P::InventoryConfigure->value) ?? false,
        ]);
    }

    /** Genehmigt einen Preisfreigabe-Antrag (nie durch den Antragsteller). */
    public function approveRequest(PriceChangeRequest $priceRequest, PriceApprovalService $approvals): RedirectResponse {
        $this->canManage();
        abort_unless($priceRequest->organization_id === $this->currentOrganization()->id, 404);

        /** @var User $approver */
        $approver = Auth::user();

        try {
            $approvals->approve($priceRequest, $approver);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.approval.flash.approved', ['price' => $priceRequest->suggested_price]));
    }

    /** Lehnt einen Preisfreigabe-Antrag mit optionaler Begründung ab. */
    public function rejectRequest(Request $request, PriceChangeRequest $priceRequest, PriceApprovalService $approvals): RedirectResponse {
        $this->canManage();
        abort_unless($priceRequest->organization_id === $this->currentOrganization()->id, 404);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        /** @var User $approver */
        $approver = Auth::user();

        try {
            $approvals->reject($priceRequest, $approver, $note);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.approval.flash.rejected'));
    }

    /** Stellt den Freigabemodus für Preisübernahmen um (direct / four_eyes). */
    public function saveApprovalMode(Request $request): RedirectResponse {
        $this->canManage();
        $mode = (string) $request->validate(['mode' => ['required', 'in:direct,four_eyes']])['mode'];

        $organization = $this->currentOrganization();
        $settings = $organization->settings ?? [];
        $settings['pricing'] = array_merge(is_array($settings['pricing'] ?? null) ? $settings['pricing'] : [], ['approval_mode' => $mode]);
        $organization->settings = $settings;
        $organization->save();

        return back()->with('success', __('procurement.approval.flash.mode_saved'));
    }

    public function create(): View {
        $this->canManage();

        return view('pricing-margin-rules._form_dialog', [
            'isDialog' => true,
            'suppliers' => Supplier::query()->orderBy('name')->limit(500)->get(),
            'roundings' => PriceRounding::cases(),
        ]);
    }

    public function store(SavePricingMarginRuleRequest $request): RedirectResponse {
        $this->canManage();
        $data = $request->validated();

        PricingMarginRule::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'name' => $data['name'],
            'supplier_id' => ! empty($data['supplier']) ? (int) $data['supplier'] : null,
            'category' => $data['category'] ?? null,
            'markup_percent' => $data['markup_percent'] ?? null,
            'target_margin' => $data['target_margin'] ?? null,
            'min_margin' => $data['min_margin'] ?? null,
            'min_sale_price' => $data['min_sale_price'] ?? null,
            'rounding' => $data['rounding'],
            'priority' => (int) ($data['priority'] ?? 0),
            'active' => (bool) ($data['active'] ?? true),
        ]);

        return redirect()->route('pricing-margin-rules.index')->with('success', __('procurement.margin.flash.created'));
    }

    public function destroy(PricingMarginRule $pricingMarginRule): RedirectResponse {
        $this->canManage();
        abort_unless($pricingMarginRule->organization_id === $this->currentOrganization()->id, 404);
        $pricingMarginRule->delete();

        return back()->with('success', __('procurement.margin.flash.deleted'));
    }

    private function canView(): void {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryConfigure->value) ?? false), 403);
    }

    private function canManage(): void {
        abort_unless(Auth::user()?->can(P::InventoryConfigure->value) ?? false, 403);
    }
}
