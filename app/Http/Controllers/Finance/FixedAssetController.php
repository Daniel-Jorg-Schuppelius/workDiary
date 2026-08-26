<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Finance\{DepreciationMethod, FixedAssetStatus};
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Accounting\{AccountingAccount, AccountingProfile, FixedAsset};
use App\Models\{Asset, Organization};
use App\Services\Accounting\{DepreciationCalculator, FixedAssetService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Anlagenregister (Feature 133, MVP-698): Voll-Höhe-Liste, Detail mit
 * AfA-Plan, Anlegen/Bearbeiten im Modal, Abgang. Lesen mit
 * `finance.accounting.view`, Pflege mit `finance.accounting.configure`.
 */
class FixedAssetController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly FixedAssetService $service,
        private readonly DepreciationCalculator $calculator,
    ) {}

    public function index(Request $request): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->currentOrganizationOrAbort();

        $status = FixedAssetStatus::tryFrom((string) $request->query('status', ''));
        $query = FixedAsset::query()
            ->where('organization_id', $organization->id)
            ->with(['asset:id,name,asset_no', 'assetAccount', 'depreciationAccount'])
            ->orderBy('asset_no');
        if ($status instanceof FixedAssetStatus) {
            $query->where('status', $status->value);
        }

        $assets = $query->get();
        $startMonth = $this->fiscalYearStartMonth($organization);
        $currentYear = $this->calculator->fiscalYearStartFor(CarbonImmutable::now(), $startMonth)->year;

        $bookValues = [];
        foreach ($assets as $asset) {
            $bookValues[$asset->id] = $this->bookValueAtYearEnd($asset, $currentYear, $startMonth);
        }
        $bookValueTotal = Money::sum(
            array_values(array_filter($bookValues, static fn (?Money $value): bool => $value instanceof Money)),
            $this->journalCurrency($organization),
        );

        return view('finance.accounting.fixed-assets', [
            'assets' => $assets,
            'bookValues' => $bookValues,
            'bookValueTotal' => $bookValueTotal,
            'status' => $status,
            'currentYear' => $currentYear,
            'activeCount' => $assets->filter(fn (FixedAsset $asset): bool => ! $asset->isDisposed())->count(),
            'canConfigure' => Gate::allows(Permission::AccountingLedgerConfigure->value),
        ]);
    }

    public function show(FixedAsset $fixedAsset): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerView->value), 403);
        $organization = $this->assertSameOrganization($fixedAsset);
        $fixedAsset->load(['asset', 'assetAccount', 'depreciationAccount', 'createdBy:id,name']);

        $rows = $this->calculator->scheduleFor($fixedAsset, $this->fiscalYearStartMonth($organization));

        return view('finance.accounting.fixed-asset', [
            'fixedAsset' => $fixedAsset,
            'rows' => $rows,
            'entries' => $this->service->entriesForSchedule($organization, $fixedAsset, $rows),
            'frozen' => $this->service->hasPostedDepreciation($fixedAsset),
            'canConfigure' => Gate::allows(Permission::AccountingLedgerConfigure->value),
        ]);
    }

    public function form(?FixedAsset $fixedAsset = null): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        if ($fixedAsset instanceof FixedAsset) {
            $this->assertSameOrganization($fixedAsset);
        }

        return view('finance.accounting._fixed_asset_dialog', [
            'fixedAsset' => $fixedAsset,
            'frozen' => $fixedAsset instanceof FixedAsset && $this->service->hasPostedDepreciation($fixedAsset),
            'methods' => DepreciationMethod::cases(),
            'accounts' => AccountingAccount::query()
                ->where('organization_id', $organization->id)
                ->active()
                ->orderBy('number')
                ->get(),
            'devices' => Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(['id', 'name', 'asset_no']),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->currentOrganizationOrAbort();
        $actor = $request->user();
        abort_if($actor === null, 403);

        $asset = $this->service->create($organization, $actor, $this->validated($request, $organization));

        return redirect()
            ->route('finance.accounting.fixed-assets.show', $asset)
            ->with('status', __('accounting.fixed_assets.flash.created', ['no' => $asset->displayNo()]));
    }

    public function update(Request $request, FixedAsset $fixedAsset): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $organization = $this->assertSameOrganization($fixedAsset);

        $this->service->update($fixedAsset, $this->validated($request, $organization));

        return back()->with('status', __('accounting.fixed_assets.flash.updated'));
    }

    public function disposeForm(FixedAsset $fixedAsset): View {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $this->assertSameOrganization($fixedAsset);

        return view('finance.accounting._fixed_asset_dispose_dialog', ['fixedAsset' => $fixedAsset]);
    }

    public function dispose(Request $request, FixedAsset $fixedAsset): RedirectResponse {
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
        $this->assertSameOrganization($fixedAsset);
        $actor = $request->user();
        abort_if($actor === null, 403);

        $data = $request->validate([
            'disposed_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->dispose($fixedAsset, CarbonImmutable::parse((string) $data['disposed_on']), $actor, $data['note'] ?? null);

        return back()->with('status', __('accounting.fixed_assets.flash.disposed'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Organization $organization): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:180'],
            'device' => ['nullable', 'string'],
            'acquired_on' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            'residual_value' => ['nullable', 'numeric', 'gte:0'],
            'useful_life_months' => ['required', 'integer', 'between:1,1200'],
            'depreciation_method' => ['required', 'string', Rule::enum(DepreciationMethod::class)],
            'asset_account' => ['nullable', 'string'],
            'depreciation_account' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $deviceId = null;
        if (! empty($data['device'])) {
            $deviceId = (int) Sqid::decodeOrNumeric(Asset::class, (string) $data['device']);
            abort_unless(Asset::query()->where('organization_id', $organization->id)->whereKey($deviceId)->exists(), 422);
        }

        return [
            'name' => (string) $data['name'],
            'asset_id' => $deviceId,
            'acquired_on' => CarbonImmutable::parse((string) $data['acquired_on'])->toDateString(),
            'acquisition_cost' => NumberHelper::roundPrecise(NumberHelper::normalizeDecimalString((string) $data['acquisition_cost']), 2),
            'residual_value' => NumberHelper::roundPrecise(NumberHelper::normalizeDecimalString((string) ($data['residual_value'] ?? '0')), 2),
            'useful_life_months' => (int) $data['useful_life_months'],
            'depreciation_method' => DepreciationMethod::from((string) $data['depreciation_method']),
            'asset_account_id' => $this->ownAccountId($organization, $data['asset_account'] ?? null),
            'depreciation_account_id' => $this->ownAccountId($organization, $data['depreciation_account'] ?? null),
            'note' => $data['note'] ?? null,
        ];
    }

    private function ownAccountId(Organization $organization, mixed $raw): ?int {
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) Sqid::decodeOrNumeric(AccountingAccount::class, (string) $raw);
        abort_unless(AccountingAccount::query()->where('organization_id', $organization->id)->whereKey($id)->exists(), 422);

        return $id;
    }

    private function fiscalYearStartMonth(Organization $organization): int {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();

        return $profile instanceof AccountingProfile ? max(1, (int) $profile->fiscal_year_start_month) : 1;
    }

    private function journalCurrency(Organization $organization): CurrencyCode {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();

        return $profile instanceof AccountingProfile ? $profile->base_currency : CurrencyCode::Euro;
    }

    /** Restbuchwert am Ende des Geschäftsjahres (vor dem Plan: AK, nach dem Plan: letzte Zeile). */
    private function bookValueAtYearEnd(FixedAsset $asset, int $fiscalYear, int $startMonth): ?Money {
        $value = $asset->acquisition_cost;
        foreach ($this->calculator->scheduleFor($asset, $startMonth) as $row) {
            if ($row->fiscalYear > $fiscalYear) {
                break;
            }
            $value = $row->bookValueEnd;
        }

        return $value;
    }

    private function assertSameOrganization(FixedAsset $asset): Organization {
        $organization = $this->currentOrganizationOrAbort();
        abort_unless((int) $asset->organization_id === (int) $organization->id, 404);

        return $organization;
    }
}
