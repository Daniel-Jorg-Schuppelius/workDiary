<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantUsageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Platform\{Organization, User};
use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilityFactorSet};
use App\Services\Metrics\OperationsMetricsService;
use App\Services\Sustainability\BranchEmissionBenchmarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-951: Nutzung je Mandant; MVP-949: anonymes Branchen-Aggregat. */
final class TenantUsageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_operator_sees_usage_of_all_tenants(): void {
        $other = Organization::factory()->create(['name' => 'Zweitmandant GmbH']);
        User::factory()->count(2)->create(['organization_id' => $other->id]);
        $operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);

        $usage = app(OperationsMetricsService::class)->tenantUsage($other);
        $this->assertSame(0, $usage['bytes']);
        $this->assertSame(0, $usage['active_users']);
        $this->assertNotNull($usage['last_activity']);

        $this->actingAs($operator)->get(route('admin.organizations.usage'))
            ->assertOk()
            ->assertSeeText(__('platform_usage.title'))
            ->assertSeeText('Zweitmandant GmbH')
            ->assertSeeText(__('platform_usage.benchmark.link'));
        $this->actingAs($operator)->get(route('admin.organizations.branch-benchmark', ['year' => 2026]))
            ->assertOk()
            ->assertSeeText(__('platform_usage.benchmark.empty', ['min' => BranchEmissionBenchmarkService::MIN_ORGANIZATIONS]));
    }

    public function test_branch_aggregate_needs_three_organizations_and_skips_demos(): void {
        $set = SustainabilityFactorSet::query()->create(['organization_id' => null, 'name' => 'Standard', 'source' => 'UBA', 'region' => 'DE', 'year' => 2026, 'active' => true]);
        $set->factors()->create(['activity_code' => 'electricity_kwh', 'label' => 'Strom', 'unit_code' => 'kg_co2e_per_kwh', 'factor' => '1.000000', 'scope' => 2, 'valid_from' => '2026-01-01', 'quality' => 'high']);
        foreach ([['A', 1000, false], ['B', 2000, false], ['C', 6000, false], ['Demo', 90000, true]] as [$name, $kwh, $demo]) {
            $organization = Organization::factory()->create(['name' => $name, 'is_demo' => $demo, 'settings' => ['branch_profile_code' => 'bau-ausbau']]);
            SustainabilityActivityRecord::query()->create(['organization_id' => $organization->id, 'activity_code' => 'electricity_kwh', 'amount' => (string) $kwh, 'unit' => 'kWh', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'data_quality' => 'measured']);
        }

        $rows = app(BranchEmissionBenchmarkService::class)->benchmark(2026);

        $this->assertSame([['branch' => 'Bau, Ausbau und Trockenbau', 'organizations' => 3, 'mean_t' => 3.0, 'median_t' => 2.0]], $rows);
    }

    public function test_org_admin_cannot_open_the_branch_comparison(): void {
        $this->actingAs($this->orgAdmin())->get(route('admin.organizations.branch-benchmark'))->assertForbidden();
    }

    public function test_org_admin_is_denied(): void {
        $this->actingAs($this->orgAdmin())->get(route('admin.organizations.usage'))->assertForbidden();
    }
}
