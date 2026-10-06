<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityScenarioController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Finance\{LiquidityScenario, LiquidityScenarioItem};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Szenarien zur Liquiditätsvorschau (MVP-954); lesen wie die Vorschau, ändern wie die Liquiditätsplanung. */
class LiquidityScenarioController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        $this->authorizeView();

        return view('reports.accounting.liquidity-scenarios', [
            'scenarios' => LiquidityScenario::query()->with('items')->orderBy('name')->get(),
            'canEdit' => Gate::allows(Permission::AccountingLedgerPrepare->value),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $this->authorizeEdit();
        $scenario = LiquidityScenario::query()->create($this->validated($request) + [
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => $this->authUser()->id,
        ]);

        return redirect()->route('reports.accounting.liquidity-scenarios.index')->with('success', __('accounting.reports.scenario.flash.saved'))->withFragment('scenario-' . $scenario->sqid);
    }

    public function update(Request $request, LiquidityScenario $scenario): RedirectResponse {
        $this->authorizeEdit();
        $scenario->update($this->validated($request));

        return back()->with('success', __('accounting.reports.scenario.flash.saved'));
    }

    public function destroy(LiquidityScenario $scenario): RedirectResponse {
        $this->authorizeEdit();
        $scenario->delete();

        return redirect()->route('reports.accounting.liquidity-scenarios.index')->with('success', __('accounting.reports.scenario.flash.deleted'));
    }

    public function storeItem(Request $request, LiquidityScenario $scenario): RedirectResponse {
        $this->authorizeEdit();
        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'expected_on' => ['required', 'date'],
        ]);
        $scenario->items()->create($data + ['organization_id' => $scenario->organization_id]);

        return back()->with('success', __('accounting.reports.scenario.flash.item_saved'));
    }

    public function destroyItem(LiquidityScenarioItem $item): RedirectResponse {
        $this->authorizeEdit();
        $item->delete();

        return back()->with('success', __('accounting.reports.scenario.flash.item_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'receipt_delay_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'inflow_change_percent' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
            'outflow_change_percent' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return [
            'name' => $data['name'],
            'receipt_delay_days' => (int) ($data['receipt_delay_days'] ?? 0),
            'inflow_change_percent' => (string) ($data['inflow_change_percent'] ?? '0'),
            'outflow_change_percent' => (string) ($data['outflow_change_percent'] ?? '0'),
            'is_including_investments' => $request->boolean('is_including_investments'),
            'note' => $data['note'] ?? null,
        ];
    }

    private function authorizeView(): void {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
    }

    /** Szenarien gelten für die ganze Organisation — ändern wie die Liquiditätsplanung (authz-a-7). */
    private function authorizeEdit(): void {
        abort_unless(Gate::allows(Permission::AccountingLedgerPrepare->value), 403);
    }
}
