<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FixedAssetClassAndSourceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Finance\{AccountType, DepreciationMethod, ProfitDetermination};
use App\Models\Accounting\{AccountingAccount, FixedAsset, FixedAssetClass};
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Models\Travel\Expense;
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsService, FiscalYearService};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-999: Anlagenklassen als Vorgabe, Anlage aus Eingangsrechnung oder Auslage. */
final class FixedAssetClassAndSourceTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private AccountingAccount $vehicles;

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
        $this->vehicles = app(ChartOfAccountsService::class)->create($this->org, ['number' => '0520', 'name' => 'Pkw', 'type' => AccountType::Asset]);
    }

    public function test_class_defaults_fill_empty_fields_and_explicit_values_win(): void {
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-asset-classes.store'), [
            'name' => 'Fuhrpark', 'useful_life_months' => '72', 'depreciation_method' => 'linear', 'asset_account' => $this->vehicles->sqid,
        ])->assertRedirect(route('finance.accounting.fixed-asset-classes.index'));
        $class = FixedAssetClass::query()->sole();
        $this->assertTrue($class->is_active);
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-asset-classes.store'), ['name' => 'Fuhrpark', 'useful_life_months' => '12', 'depreciation_method' => 'linear'])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-asset-classes.index'))->assertOk()->assertSee('Fuhrpark');

        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), [
            'name' => 'Transporter', 'fixed_asset_class' => $class->sqid, 'acquired_on' => '2026-02-01', 'acquisition_cost' => '36000', 'useful_life_months' => '', 'depreciation_method' => '',
        ])->assertRedirect();
        $asset = FixedAsset::query()->where('name', 'Transporter')->sole();
        $this->assertSame(72, $asset->useful_life_months);
        $this->assertSame(DepreciationMethod::Linear, $asset->depreciation_method);
        $this->assertSame($this->vehicles->id, $asset->asset_account_id);
        $this->assertSame($class->id, $asset->fixed_asset_class_id);

        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), [
            'name' => 'Pkw kurz', 'fixed_asset_class' => $class->sqid, 'acquired_on' => '2026-02-01', 'acquisition_cost' => '20000', 'useful_life_months' => '36', 'depreciation_method' => 'linear',
        ])->assertRedirect();
        $this->assertSame(36, FixedAsset::query()->where('name', 'Pkw kurz')->sole()->useful_life_months);

        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), [
            'name' => 'Ohne Klasse', 'acquired_on' => '2026-02-01', 'acquisition_cost' => '5000', 'useful_life_months' => '', 'depreciation_method' => 'linear',
        ])->assertSessionHasErrors('useful_life_months');
    }

    public function test_incoming_invoice_and_expense_become_a_fixed_asset_once(): void {
        $incoming = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id, 'document_id' => Document::factory()->create(['created_by_user_id' => $this->admin->id])->id,
            'sha256' => hash('sha256', 'er-1'), 'source' => 'upload', 'received_at' => now(), 'status' => 'approved',
            'invoice_number' => 'ER-4711', 'seller_name' => 'Autohaus Nord', 'issue_date' => '2026-03-10', 'currency' => 'EUR',
            'amount_net' => '30000.00', 'amount_gross' => '35700.00',
        ]);
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.create', ['source_kind' => 'incoming', 'source_ref' => $incoming->sqid]))
            ->assertOk()->assertSee('Autohaus Nord · ER-4711')->assertSee('30000.00')->assertSee('2026-03-10');

        $payload = ['name' => 'Autohaus Nord · ER-4711', 'acquired_on' => '2026-03-10', 'acquisition_cost' => '30000', 'useful_life_months' => '72', 'depreciation_method' => 'linear',
            'source_kind' => 'incoming', 'source_ref' => $incoming->sqid];
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), $payload)->assertRedirect();
        $asset = FixedAsset::query()->sole();
        $this->assertSame($incoming->getMorphClass(), $asset->source_type);
        $this->assertSame($incoming->id, $asset->source_id);
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), $payload)->assertSessionHasErrors('source_ref');
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.show', $asset))->assertOk()
            ->assertSee(__('accounting.fixed_assets.source.incoming', ['number' => 'ER-4711']));

        $expense = Expense::factory()->create(['organization_id' => $this->org->id, 'user_id' => $this->admin->id, 'status' => ExpenseStatus::Approved,
            'vendor' => 'Elektromarkt', 'description' => 'Notebook', 'amount_net' => '1500.00', 'date' => now()->toDateString()]);
        $this->actingAs($this->admin)->get(route('finance.accounting.fixed-assets.create', ['source_kind' => 'expense', 'source_ref' => $expense->sqid]))
            ->assertOk()->assertSee('Elektromarkt · Notebook')->assertSee('1500.00');
        $this->actingAs($this->admin)->get(route('expenses.index'))->assertOk()
            ->assertSee(e(route('finance.accounting.fixed-assets.create', ['source_kind' => 'expense', 'source_ref' => $expense->sqid])), false);

        $foreign = Expense::factory()->create(['organization_id' => Organization::factory()->create()->id, 'status' => ExpenseStatus::Approved]);
        $this->actingAs($this->admin)->post(route('finance.accounting.fixed-assets.store'), [
            'name' => 'Fremd', 'acquired_on' => '2026-04-02', 'acquisition_cost' => '1000', 'useful_life_months' => '36', 'depreciation_method' => 'linear',
            'source_kind' => 'expense', 'source_ref' => $foreign->sqid,
        ])->assertStatus(422);
    }
}
