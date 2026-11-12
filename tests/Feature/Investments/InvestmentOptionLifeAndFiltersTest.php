<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentOptionLifeAndFiltersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Enums\AssetFinance\AssetFinanceKind;
use App\Enums\Investments\{InvestmentBudgetRequestStatus, InvestmentCaseStatus};
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Asset\Asset;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Finance\CostCenter;
use App\Models\Inventory\Warehouse;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase};
use App\Models\Platform\User;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Supplier\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1606: Nutzungsdauer der Variante, Lieferant der Anschaffung am Asset,
 * Filter der Investitionsliste, PDF-Bericht und Bestellbezug am Leasingvertrag.
 */
final class InvestmentOptionLifeAndFiltersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function investment(array $attributes = []): InvestmentCase {
        return InvestmentCase::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Ersatz Kompressor',
            'category' => 'machine',
            'urgency' => 'medium',
            'status' => InvestmentCaseStatus::Idea,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function budget(InvestmentCase $case, InvestmentBudgetRequestStatus $status): InvestmentBudgetRequest {
        return InvestmentBudgetRequest::query()->create([
            'organization_id' => $this->organization->id,
            'investment_case_id' => $case->id,
            'version' => (int) $case->budgetRequests()->max('version') + 1,
            'amount' => '5000.00',
            'cost_kind' => 'purchase',
            'financing' => 'cash',
            'status' => $status,
            'requested_by' => $this->admin->id,
        ]);
    }

    public function test_option_takes_the_useful_life_and_the_comparison_shows_it(): void {
        $case = $this->investment();

        $this->actingAs($this->admin)->get(route('investments.show', $case))
            ->assertOk()
            ->assertSee('name="useful_life_years"', false);

        $this->actingAs($this->admin)->post(route('investments.options.store', $case), [
            'title' => 'Angebot A',
            'one_time_cost' => '4200',
            'useful_life_years' => 8,
        ])->assertSessionHas('success');
        $this->assertSame(8, $case->options()->sole()->useful_life_years);

        $this->actingAs($this->admin)->get(route('investments.show', $case))
            ->assertOk()
            ->assertSee(trans_choice('investment.option.years', 8, ['count' => 8]));
    }

    public function test_linked_asset_gets_the_supplier_of_the_recommended_option(): void {
        $case = $this->investment(['status' => InvestmentCaseStatus::Approved]);
        $this->budget($case, InvestmentBudgetRequestStatus::Approved);
        $first = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Druckluft Nord']);
        $recommended = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kompressoren Süd']);
        $case->options()->create(['organization_id' => $this->organization->id, 'title' => 'A', 'supplier_id' => $first->id, 'one_time_cost' => '4000']);
        $case->options()->create(['organization_id' => $this->organization->id, 'title' => 'B', 'supplier_id' => $recommended->id, 'one_time_cost' => '4200', 'recommended' => true]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $kept = Asset::factory()->create(['organization_id' => $this->organization->id, 'acquired_from_supplier_id' => $first->id]);

        foreach ([$asset, $kept] as $target) {
            $this->actingAs($this->admin)->post(route('investments.links.store', $case), ['linkable_type' => 'asset', 'linkable_sqid' => $target->sqid])
                ->assertSessionHas('success');
        }

        $this->assertSame($recommended->id, (int) $asset->fresh()->acquired_from_supplier_id);
        // Ein gepflegter Lieferant bleibt.
        $this->assertSame($first->id, (int) $kept->fresh()->acquired_from_supplier_id);

        $this->actingAs($this->admin)->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee(__('asset.acquisition.supplier'))
            ->assertSee('Kompressoren Süd');
    }

    public function test_list_filters_by_period_responsible_cost_center_budget_and_risk(): void {
        $costCenter = CostCenter::query()->create(['organization_id' => $this->organization->id, 'code' => '4711', 'label' => 'Werkstatt', 'active' => true]);
        $other = User::factory()->create(['organization_id' => $this->organization->id]);
        $match = $this->investment([
            'title' => 'Trefferakte',
            'starts_on' => '2027-03-01',
            'ends_on' => '2027-06-30',
            'responsible_user_id' => $this->admin->id,
            'cost_center_id' => $costCenter->id,
            'urgency' => 'high',
            'risk_note' => 'Lieferzeit unsicher',
        ]);
        $this->budget($match, InvestmentBudgetRequestStatus::Rejected);
        $this->budget($match, InvestmentBudgetRequestStatus::InApproval);
        $miss = $this->investment([
            'title' => 'Andere Akte',
            'starts_on' => '2028-01-01',
            'ends_on' => '2028-02-01',
            'responsible_user_id' => $other->id,
        ]);

        $filters = [
            ['from' => '2027-04-01', 'to' => '2027-04-30'],
            ['responsible' => $this->admin->sqid],
            ['cost_center' => $costCenter->sqid],
            ['budget' => 'in_approval'],
            ['urgency' => 'high'],
            ['risk' => 1],
        ];
        foreach ($filters as $filter) {
            $this->actingAs($this->admin)->get(route('investments.index', $filter))
                ->assertOk()
                ->assertSee('Trefferakte')
                ->assertDontSee('Andere Akte');
        }

        // Budgetstatus meint den jüngsten Antrag: der abgelehnte ältere zählt nicht.
        $this->actingAs($this->admin)->get(route('investments.index', ['budget' => 'rejected']))
            ->assertOk()->assertDontSee('Trefferakte');
        $this->actingAs($this->admin)->get(route('investments.index', ['budget' => 'none']))
            ->assertOk()->assertSee('Andere Akte')->assertDontSee('Trefferakte');
        // Unlesbare Filterwerte gelten als nicht gesetzt.
        $this->actingAs($this->admin)->get(route('investments.index', ['from' => 'gestern', 'budget' => 'x', 'urgency' => 'x']))
            ->assertOk()->assertSee('Trefferakte')->assertSee('Andere Akte');
        $this->assertNotNull($miss->id);
    }

    public function test_report_exports_a_pdf(): void {
        $case = $this->investment(['status' => InvestmentCaseStatus::Approved]);
        $this->budget($case, InvestmentBudgetRequestStatus::Approved);

        $response = $this->actingAs($this->admin)->get(route('investments.report', ['export' => 'pdf']));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_leasing_contract_takes_the_purchase_order_from_the_form(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $order = PurchaseOrder::query()->create([
            'organization_id' => $this->organization->id,
            'number' => 'BE-LEAS-1',
            'supplier_id' => $supplier->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->organization->id])->id,
            'status' => PurchaseOrderStatus::Draft->value,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->admin)->get(route('asset-finance.create'))
            ->assertOk()
            ->assertSee('name="purchase_order_id"', false)
            ->assertSee('BE-LEAS-1');

        $this->actingAs($this->admin)->post(route('asset-finance.store'), [
            'kind' => AssetFinanceKind::FinanceLease->value,
            'partner_name' => 'Leasingbank Beispiel',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYears(3)->toDateString(),
            'payment_rhythm' => 'monthly',
            'purchase_order_id' => $order->sqid,
        ])->assertSessionHasNoErrors();

        $contract = AssetFinanceContract::query()->where('partner_name', 'Leasingbank Beispiel')->sole();
        $this->assertSame($order->id, (int) $contract->purchase_order_id);

        $this->actingAs($this->admin)->get(route('asset-finance.show', $contract))
            ->assertOk()
            ->assertSee('BE-LEAS-1');
        $this->actingAs($this->admin)->get(route('asset-finance.edit', $contract))
            ->assertOk()
            ->assertSee('value="' . $order->sqid . '" selected', false);
    }
}
