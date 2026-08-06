<?php
/*
 * Created on   : Wed Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialCostAllocationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Customers;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\{IssueStockForCustomerRequest, SaveMaterialCostAllocationRequest};
use App\Models\{ArticleVariant, Customer, LexofficeVoucher, MaterialCostAllocation, Warehouse};
use App\Services\Inventory\CustomerStockAllocationService;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Materialkosten-Zuordnung an der Kundenakte: freie Beträge oder Anteile aus
 * Lexoffice-Einkaufsbelegen einem Kunden (optional Projekt) zuordnen — Basis der
 * Gewinndarstellung (Umsatz − Materialkosten).
 */
class MaterialCostAllocationController extends Controller {
    /** Modal-Fragment zum Anlegen (data-entry-modal-trigger). */
    public function create(Customer $customer): View {
        Gate::authorize('update', $customer);

        return view('customers.material._form_dialog', [
            'customer' => $customer,
            'purchaseVouchers' => LexofficeVoucher::query()
                ->where('voucher_type', 'purchaseinvoice')
                ->where('archived', false)
                ->orderByDesc('voucher_date')
                ->limit(200)
                ->get(),
            'projects' => $customer->projects()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveMaterialCostAllocationRequest $request, Customer $customer): RedirectResponse {
        Gate::authorize('update', $customer);

        $voucher = null;
        $voucherId = $request->validated('voucher_id');
        if ($voucherId !== null && $voucherId !== '') {
            $voucher = LexofficeVoucher::query()->whereKey($voucherId)->first();
        }

        $description = trim((string) $request->validated('description', ''));
        if ($description === '' && $voucher !== null) {
            $description = trim(($voucher->voucher_number ? $voucher->voucher_number . ' · ' : '') . __('customer-material.source_lexoffice'));
        }

        $customer->materialCostAllocations()->create([
            'organization_id' => $customer->organization_id,
            'project_id' => $request->validated('project_id'),
            'source_type' => $voucher !== null ? LexofficeVoucher::class : null,
            'source_id' => $voucher?->getKey(),
            'description' => $description !== '' ? $description : null,
            'allocated_amount' => $request->validated('allocated_amount'),
            'currency' => $customer->currency->value,
            'allocated_on' => $request->validated('allocated_on'),
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('customer-material.flash_saved'));
    }

    public function destroy(
        Customer $customer,
        MaterialCostAllocation $allocation,
        CustomerStockAllocationService $service,
    ): RedirectResponse {
        Gate::authorize('update', $customer);

        // Fremd-Zuordnung (anderer Kunde) nie über diese Route löschbar.
        abort_unless((int) $allocation->customer_id === (int) $customer->getKey(), 404);

        // Aus einer Lagerentnahme entstandene Zuordnung wird zurückgebucht
        // (Zugang ins Lager); freie/Beleg-Zuordnungen werden nur entfernt.
        $service->reverse($allocation);

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('customer-material.flash_deleted'));
    }

    /** Modal-Fragment für die Lagerentnahme (nur mit aktivem Lagermodul). */
    public function createStock(Customer $customer): View {
        Gate::authorize('update', $customer);
        $this->ensureInventoryModule();
        Gate::authorize(Permission::InventoryPost->value);

        return view('customers.material._stock_form_dialog', [
            'customer' => $customer,
            'warehouses' => Warehouse::query()->where('active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'variants' => ArticleVariant::query()->with('article:id,name')
                ->where('status', \App\Enums\Article\ArticleStatus::Active->value)
                ->orderBy('id')->limit(500)->get(),
            'projects' => $customer->projects()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeStock(
        IssueStockForCustomerRequest $request,
        Customer $customer,
        CustomerStockAllocationService $service,
    ): RedirectResponse {
        Gate::authorize('update', $customer);
        $this->ensureInventoryModule();
        Gate::authorize(Permission::InventoryPost->value);

        /** @var ArticleVariant $variant */
        $variant = ArticleVariant::query()->findOrFail($request->validated('variant_id'));
        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($request->validated('warehouse_id'));

        try {
            $service->issueForCustomer(
                $customer,
                $variant,
                $warehouse,
                (string) $request->validated('qty'),
                $request->validated('project_id'),
                $request->validated('allocated_on'),
                Auth::id() !== null ? (int) Auth::id() : null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('customer-material.flash_stock_issued'));
    }

    /** Ohne aktives Lagermodul existiert die Lagerentnahme nicht (404). */
    private function ensureInventoryModule(): void {
        abort_unless(app(FeatureFlagResolver::class)->isEnabled('module.lager'), 404);
    }
}
