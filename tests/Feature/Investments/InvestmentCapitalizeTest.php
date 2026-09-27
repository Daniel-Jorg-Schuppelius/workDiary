<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentCapitalizeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Investments;

use App\Models\Accounting\FixedAsset;
use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase, InvestmentLink, InvestmentOption};
use App\Models\Platform\User;
use App\Services\Investments\Contracts\{AssetCapitalizer, NullAssetCapitalizer};
use App\Services\Investments\InvestmentService;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-909: genehmigte Investition als Anlage übernehmen, über den Contract zur Anlagenbuchhaltung. */
final class InvestmentCapitalizeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private InvestmentCase $case;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->case = InvestmentCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Neue Presse', 'category' => 'machine', 'status' => 'approved', 'created_by' => $this->admin->id]);
        InvestmentOption::query()->create(['organization_id' => $this->organization->id, 'investment_case_id' => $this->case->id, 'title' => 'Presse P200', 'one_time_cost' => '48000.00', 'recurring_cost_yearly' => '0', 'useful_life_years' => 10, 'recommended' => true]);
    }

    private function approve(): void {
        InvestmentBudgetRequest::query()->create(['organization_id' => $this->organization->id, 'investment_case_id' => $this->case->id, 'version' => 1, 'amount' => '50000.00', 'cost_kind' => 'purchase', 'financing' => 'cash', 'status' => 'approved', 'requested_by' => $this->admin->id]);
    }

    public function test_approved_investment_becomes_a_linked_fixed_asset(): void {
        $this->actingAs($this->admin)->get(route('investments.show', $this->case))->assertOk()->assertDontSee(route('investments.capitalize.create', $this->case), false);
        $this->actingAs($this->admin)->get(route('investments.capitalize.create', $this->case))->assertNotFound();

        $this->approve();
        $this->actingAs($this->admin)->get(route('investments.show', $this->case))->assertSee(route('investments.capitalize.create', $this->case), false);
        $this->actingAs($this->admin)->get(route('investments.capitalize.create', $this->case))->assertOk()->assertSee('Presse P200')->assertSee('value="120"', false);

        $this->actingAs($this->admin)->post(route('investments.capitalize.store', $this->case), ['name' => 'Presse P200', 'acquired_on' => '2026-09-01', 'acquisition_cost' => '47500.00', 'useful_life_months' => 120])
            ->assertRedirect(route('investments.show', $this->case));

        $asset = FixedAsset::query()->where('name', 'Presse P200')->firstOrFail();
        $this->assertTrue(MorphMap::is($asset->source_type, InvestmentCase::class));
        $this->assertSame($this->case->id, (int) $asset->source_id);
        $this->assertTrue(InvestmentLink::query()->where('investment_case_id', $this->case->id)->where('linkable_id', $asset->id)->exists());
        $this->assertSame(47500.0, app(InvestmentService::class)->projection($this->case->fresh())['actual']);
        $this->actingAs($this->admin)->get(route('investments.show', $this->case))->assertDontSee(route('investments.capitalize.create', $this->case), false);
    }

    public function test_without_fixed_asset_accounting_the_path_is_closed(): void {
        $this->approve();
        $this->app->instance(AssetCapitalizer::class, new NullAssetCapitalizer);

        $this->actingAs($this->admin)->get(route('investments.show', $this->case))->assertOk()->assertDontSee(route('investments.capitalize.create', $this->case), false);
        $this->actingAs($this->admin)->post(route('investments.capitalize.store', $this->case), ['name' => 'X', 'acquired_on' => '2026-09-01', 'acquisition_cost' => '1', 'useful_life_months' => 12])->assertNotFound();
    }
}
