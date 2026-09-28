<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetClassController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\DepreciationMethod;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ResolvesOwnAccount;
use App\Models\Accounting\{AccountingAccount, FixedAssetClass};
use App\Models\Platform\Organization;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Anlagenklassen (Feature 133, MVP-999): Vorgaben für neue Anlagen, deaktivieren statt löschen. */
class FixedAssetClassController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesOwnAccount;

    public function index(): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        return view('finance.accounting.fixed-asset-classes', [
            'classes' => FixedAssetClass::query()->where('organization_id', $organization->id)
                ->with(['assetAccount', 'depreciationAccount'])->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function form(?FixedAssetClass $fixedAssetClass = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_if($fixedAssetClass !== null && (int) $fixedAssetClass->organization_id !== (int) $organization->id, 404);

        return view('finance.accounting._fixed_asset_class_dialog', [
            'assetClass' => $fixedAssetClass,
            'methods' => DepreciationMethod::cases(),
            'accounts' => AccountingAccount::query()->where('organization_id', $organization->id)->active()->orderBy('number')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        FixedAssetClass::query()->create($this->validated($request, $organization, null) + [
            'organization_id' => $organization->id,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('finance.accounting.fixed-asset-classes.index')->with('status', __('accounting.fixed_assets.classes.flash.saved'));
    }

    public function update(Request $request, FixedAssetClass $fixedAssetClass): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $fixedAssetClass->organization_id === (int) $organization->id, 404);
        $fixedAssetClass->update($this->validated($request, $organization, $fixedAssetClass));

        return redirect()->route('finance.accounting.fixed-asset-classes.index')->with('status', __('accounting.fixed_assets.classes.flash.saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Organization $organization, ?FixedAssetClass $class): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('fixed_asset_classes', 'name')->where('organization_id', $organization->id)->ignore($class?->id)],
            'useful_life_months' => ['required', 'integer', 'between:1,1200'],
            'depreciation_method' => ['required', Rule::enum(DepreciationMethod::class)],
            'asset_account' => ['nullable', 'string'],
            'depreciation_account' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            'name' => (string) $data['name'],
            'useful_life_months' => (int) $data['useful_life_months'],
            'depreciation_method' => DepreciationMethod::from((string) $data['depreciation_method']),
            'asset_account_id' => $this->ownAccountId($organization, $data['asset_account'] ?? null),
            'depreciation_account_id' => $this->ownAccountId($organization, $data['depreciation_account'] ?? null),
            'is_active' => $request->boolean('is_active', $class === null),
            'note' => $data['note'] ?? null,
        ];
    }
}
