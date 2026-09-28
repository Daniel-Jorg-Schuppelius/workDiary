<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SpecialDepreciationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{AccountType, AccountingEntryStatus, PostingAccountRole, PostingSourceKind, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingFiscalYear, AccountingPostingRule, FixedAsset, FixedAssetSpecialDepreciation};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, DepreciationCalculator, FiscalYearService, FixedAssetService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** MVP-980/981: degressive AfA im Formular und Sonder-AfA § 7g mit Grenzen und Buchungssperre. */
final class SpecialDepreciationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    /** @var array<string, AccountingAccount> */
    private array $accounts = [];

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry, 'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1, 'starts_on' => CarbonImmutable::parse('2026-01-01'), 'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, CarbonImmutable::parse('2026-01-01'));
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);
        $chart = app(ChartOfAccountsService::class);
        $this->accounts['bga'] = $chart->create($this->org, ['number' => '0410', 'name' => 'BGA', 'type' => AccountType::Asset]);
        $this->accounts['depreciation'] = $chart->create($this->org, ['number' => '4830', 'name' => 'Abschreibungen', 'type' => AccountType::Expense]);
        foreach ([PostingAccountRole::FixedAsset->value => 'bga', PostingAccountRole::Depreciation->value => 'depreciation'] as $role => $account) {
            AccountingPostingRule::query()->create([
                'organization_id' => $this->org->id, 'source_kind' => PostingSourceKind::Depreciation, 'role' => PostingAccountRole::from($role),
                'accounting_account_id' => $this->accounts[$account]->id, 'priority' => 100, 'version' => 1, 'valid_from' => '2026-01-01', 'is_active' => true,
            ]);
        }
    }

    private function machine(): FixedAsset {
        return app(FixedAssetService::class)->create($this->org, $this->admin, [
            'name' => 'Maschine', 'acquired_on' => '2026-01-01', 'acquisition_cost' => '10000.00', 'residual_value' => '0.00', 'useful_life_months' => 120,
        ]);
    }

    public function test_form_accepts_a_declining_rate_only_within_the_statutory_limits(): void {
        $payload = ['name' => 'Bagger', 'acquired_on' => '2026-01-01', 'acquisition_cost' => '10000.00', 'residual_value' => '0', 'useful_life_months' => 120, 'depreciation_method' => 'declining'];

        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), $payload + ['declining_rate' => '35'])->assertSessionHasErrors('declining_rate');
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), ['acquired_on' => '2023-06-01', 'declining_rate' => '20'] + $payload)->assertSessionHasErrors('depreciation_method');
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), $payload + ['declining_rate' => '30'])->assertRedirect();

        $asset = FixedAsset::query()->sole();
        $this->assertSame('30.00', (string) $asset->declining_rate);
        $this->assertSame('3000.00', app(DepreciationCalculator::class)->amountForYear($asset, 2026)->getAmount());
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.show', $asset))->assertOk()->assertSeeText('30,00 %');
    }

    public function test_special_depreciation_adds_to_the_regular_rate_and_spreads_the_rest_afterwards(): void {
        $asset = $this->machine();
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.special.store', $asset), ['fiscal_year' => 2026, 'depreciation_amount' => '2000'])->assertSessionHas('status');
        app(FixedAssetService::class)->saveSpecialDepreciation($asset, 2027, '2000.00', null, $this->admin);

        $rows = app(DepreciationCalculator::class)->scheduleFor($asset->fresh());
        $amounts = array_map(static fn ($row): string => $row->amount->getAmount(), $rows);
        $this->assertSame(['3000.00', '3000.00', '1000.00', '1000.00', '1000.00', '200.00', '200.00', '200.00', '200.00', '200.00'], $amounts);
        $this->assertSame('2000.00', $rows[0]->special?->getAmount());
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.show', $asset))->assertOk()->assertSeeText(__('accounting.fixed_assets.special.column'));

        try {
            app(FixedAssetService::class)->saveSpecialDepreciation($asset, 2028, '500.00', null, $this->admin);
            $this->fail('Mehr als 40 % Sonder-AfA wurden angenommen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('depreciation_amount', $e->errors());
        }
        $this->expectException(ValidationException::class);
        app(FixedAssetService::class)->saveSpecialDepreciation($asset, 2031, '100.00', null, $this->admin);
    }

    public function test_special_depreciation_is_locked_once_the_year_is_prepared_and_flows_into_the_posting(): void {
        $asset = $this->machine();
        app(FixedAssetService::class)->saveSpecialDepreciation($asset, 2026, '1500.00', 'Investitionsabzugsbetrag', $this->admin);
        $year = AccountingFiscalYear::query()->where('organization_id', $this->org->id)->sole();
        $this->actingAs($this->admin)->post(route('finance.accounting.closing.depreciation', $year))->assertRedirect();

        $entry = AccountingEntry::query()->where('source_key', 'depreciation:' . $asset->id . ':2026')->sole();
        $this->assertSame(AccountingEntryStatus::Ready, $entry->status);
        $this->assertSame('2500.00', $entry->lines()->where('accounting_account_id', $this->accounts['depreciation']->id)->sole()->debit?->getAmount());

        $special = FixedAssetSpecialDepreciation::query()->sole();
        $this->actingAs($this->admin)->delete(route('finance.accounting.fixed-assets.special.destroy', [$asset, $special]))->assertSessionHasErrors('fiscal_year');
        $this->assertSame(1, FixedAssetSpecialDepreciation::query()->count());
    }
}
