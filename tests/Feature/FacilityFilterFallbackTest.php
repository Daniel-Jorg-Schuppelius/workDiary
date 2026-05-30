<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : GitHub Copilot
 * Filename     : FacilityFilterFallbackTest.php
 * License      : AGPL-3.0-or-later
 */

namespace Tests\Feature;

use App\Models\{Building, Customer, Floor, Site, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class FacilityFilterFallbackTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_sites_index_accepts_numeric_customer_filter_fallback(): void {
        $customerA = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $customerB = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $siteA = Site::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customerA->id,
            'name' => 'Alpha Standort',
        ]);
        Site::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customerB->id,
            'name' => 'Beta Standort',
        ]);

        $this->actingAs($this->admin)
            ->get(route('sites.index', ['customer' => (string) $customerA->id]))
            ->assertOk()
            ->assertViewHas('sites', static function ($sites) use ($siteA): bool {
                $items = $sites->items();
                return count($items) === 1 && (int) $items[0]->id === (int) $siteA->id;
            });
    }

    public function test_floors_index_accepts_numeric_building_filter_fallback(): void {
        $site = Site::factory()->create(['organization_id' => $this->organization->id]);
        $buildingA = Building::factory()->create([
            'organization_id' => $this->organization->id,
            'site_id' => $site->id,
            'name' => 'Gebaeude A',
        ]);
        $buildingB = Building::factory()->create([
            'organization_id' => $this->organization->id,
            'site_id' => $site->id,
            'name' => 'Gebaeude B',
        ]);

        $floorA = Floor::factory()->create([
            'organization_id' => $this->organization->id,
            'building_id' => $buildingA->id,
            'label' => 'A-EG',
        ]);
        Floor::factory()->create([
            'organization_id' => $this->organization->id,
            'building_id' => $buildingB->id,
            'label' => 'B-EG',
        ]);

        $this->actingAs($this->admin)
            ->get(route('floors.index', ['building' => (string) $buildingA->id]))
            ->assertOk()
            ->assertViewHas('floors', static function ($floors) use ($floorA): bool {
                $items = $floors->items();
                return count($items) === 1 && (int) $items[0]->id === (int) $floorA->id;
            });
    }

    public function test_buildings_index_accepts_numeric_site_filter_fallback(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $siteA = Site::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        $siteB = Site::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);

        $buildingA = Building::factory()->create([
            'organization_id' => $this->organization->id,
            'site_id' => $siteA->id,
        ]);
        Building::factory()->create([
            'organization_id' => $this->organization->id,
            'site_id' => $siteB->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('buildings.index', ['site' => (string) $siteA->id]))
            ->assertOk()
            ->assertViewHas('buildings', static function ($buildings) use ($buildingA): bool {
                $items = $buildings->items();
                return count($items) === 1 && (int) $items[0]->id === (int) $buildingA->id;
            });
    }
}
