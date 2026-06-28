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
use App\Models\{PricingMarginRule, Supplier};
use Illuminate\Http\{RedirectResponse};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Margenregeln für Verkaufspreisvorschläge (Feature 050, MVP-095). Modul-Gating
 * über `pricing-margin-rules.*` → module.lager; lesen mit inventory.viewAny,
 * verwalten mit inventory.configure.
 */
class PricingMarginRuleController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        $this->canView();

        return view('pricing-margin-rules.index', [
            'rules' => PricingMarginRule::query()->with('supplier')->orderByDesc('priority')->orderBy('name')->paginate(50),
        ]);
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
