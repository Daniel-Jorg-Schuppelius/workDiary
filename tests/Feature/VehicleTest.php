<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Vehicle\VehiclePropulsion;
use App\Enums\Vehicle\VehicleType;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class VehicleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_renders(): void {
        $this->actingAs($this->user);
        Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $this->get(route('vehicles.index'))->assertOk()->assertSee(__('Fuhrpark'));
    }

    public function test_store_creates_vehicle(): void {
        $this->actingAs($this->user);

        $this->post(route('vehicles.store'), [
            'license_plate' => 'B-AB 123',
            'label' => 'Sprinter Werkstatt',
            'vehicle_type' => VehicleType::Van->value,
            'propulsion' => VehiclePropulsion::Diesel->value,
            'default_rate_per_km' => '0.3500',
            'tank_capacity_liters' => 75,
            'odometer_km' => 12000,
        ])->assertRedirect(route('vehicles.index'));

        $vehicle = Vehicle::query()->firstOrFail();
        $this->assertSame('B-AB 123', $vehicle->license_plate);
        $this->assertSame($this->organization->id, $vehicle->organization_id);
        $this->assertSame('0.3500', $vehicle->default_rate_per_km);
    }

    public function test_destroy_archives_vehicle(): void {
        $this->actingAs($this->user);
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

        $this->delete(route('vehicles.destroy', $vehicle))->assertRedirect();
        $this->assertNotNull($vehicle->fresh()->archived_at);
    }

    public function test_non_owner_cannot_update_assigned_vehicle(): void {
        $owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'default_user_id' => $owner->id,
        ]);

        $this->actingAs($this->user);
        $this->put(route('vehicles.update', $vehicle), [
            'license_plate' => $vehicle->license_plate,
            'label' => 'hacked',
            'vehicle_type' => $vehicle->vehicle_type->value,
            'propulsion' => $vehicle->propulsion->value,
            'default_user_id' => $owner->id,
        ])->assertForbidden();
    }

    public function test_admin_can_update_any_vehicle(): void {
        $owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'default_user_id' => $owner->id,
            'label' => 'old',
        ]);

        $this->actingAs($this->admin);
        $this->put(route('vehicles.update', $vehicle), [
            'license_plate' => $vehicle->license_plate,
            'label' => 'admin-changed',
            'vehicle_type' => $vehicle->vehicle_type->value,
            'propulsion' => $vehicle->propulsion->value,
            'default_user_id' => $owner->id,
        ])->assertRedirect();

        $this->assertSame('admin-changed', $vehicle->fresh()->label);
    }
}
