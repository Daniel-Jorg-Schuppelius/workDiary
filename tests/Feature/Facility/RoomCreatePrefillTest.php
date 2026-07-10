<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomCreatePrefillTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Facility;

use App\Enums\User\UserRole;
use App\Models\{Building, Customer, Floor, Site, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Stellt sicher, dass der "Raum anlegen"-Dialog die Verortung (Customer →
 * Site → Building → Floor) aus dem Drilldown-Kontext vorbelegt.
 */
class RoomCreatePrefillTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->user->assignRole(UserRole::TrainingManager->value);
    }

    public function test_create_prefills_full_chain_from_floor_query(): void {
        [$customer, $site, $building, $floor] = $this->makeChain();

        $response = $this->actingAs($this->user)
            ->get(route('rooms.create', ['floor' => $floor->sqid]));

        $response->assertOk();
        $response->assertViewHas('prefill', [
            'customer_id' => $customer->id,
            'site_id'     => $site->id,
            'building_id' => $building->id,
            'floor_id'    => $floor->id,
        ]);
    }

    public function test_create_prefills_chain_from_building_query(): void {
        [$customer, $site, $building] = $this->makeChain();

        $response = $this->actingAs($this->user)
            ->get(route('rooms.create', ['building' => $building->sqid]));

        $response->assertOk();
        $response->assertViewHas('prefill', [
            'customer_id' => $customer->id,
            'site_id'     => $site->id,
            'building_id' => $building->id,
            'floor_id'    => null,
        ]);
    }

    public function test_create_prefills_chain_from_numeric_building_query_fallback(): void {
        [$customer, $site, $building] = $this->makeChain();

        $response = $this->actingAs($this->user)
            ->get(route('rooms.create', ['building' => (string) $building->id]));

        $response->assertOk();
        $response->assertViewHas('prefill', [
            'customer_id' => $customer->id,
            'site_id'     => $site->id,
            'building_id' => $building->id,
            'floor_id'    => null,
        ]);
    }

    public function test_create_without_query_returns_empty_prefill(): void {
        $response = $this->actingAs($this->user)->get(route('rooms.create'));

        $response->assertOk();
        $response->assertViewHas('prefill', [
            'customer_id' => null,
            'site_id'     => null,
            'building_id' => null,
            'floor_id'    => null,
        ]);
    }

    /**
     * @return array{0: Customer, 1: Site, 2: Building, 3: Floor}
     */
    private function makeChain(): array {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $site = Site::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id'     => $customer->id,
        ]);
        $building = Building::factory()->create([
            'organization_id' => $this->organization->id,
            'site_id'         => $site->id,
        ]);
        $floor = Floor::factory()->create([
            'organization_id' => $this->organization->id,
            'building_id'     => $building->id,
        ]);

        return [$customer, $site, $building, $floor];
    }
}
