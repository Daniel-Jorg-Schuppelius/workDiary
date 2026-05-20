<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalVehicleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Travel\TravelLogVehicle;
use App\Enums\Vehicle\VehicleOwnership;
use App\Enums\Vehicle\VehiclePropulsion;
use App\Enums\Vehicle\VehicleType;
use App\Models\DiaryEntry;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Routing\TourService;
use Carbon\CarbonImmutable;
use Database\Seeders\EntryTypeSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class RentalVehicleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(EntryTypeSeeder::class);
        $this->setUpOrganization();
        Config::set('timesheet.travel.auto_create_time_entry', false);
    }

    public function test_vehicle_form_accepts_rental_fields(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->post(route('vehicles.store'), [
                'license_plate' => 'M-RT 1',
                'label' => 'Mietsprinter',
                'vehicle_type' => VehicleType::Van->value,
                'propulsion' => VehiclePropulsion::Diesel->value,
                'ownership' => VehicleOwnership::Rental->value,
                'rental_provider' => 'Sixt',
                'rental_start' => '2026-05-01',
                'rental_end' => '2026-05-31',
                'rental_cost_per_day' => '49.90',
                'rental_included_km' => 1500,
                'rental_extra_cost_per_km' => '0.2500',
            ])
            ->assertRedirect(route('vehicles.index'));

        $vehicle = Vehicle::query()->firstOrFail();
        $this->assertSame('rental', $vehicle->ownership->value);
        $this->assertSame('Sixt', $vehicle->rental_provider);
        $this->assertSame(1500, $vehicle->rental_included_km);
        $this->assertSame('0.2500', $vehicle->rental_extra_cost_per_km);
        $this->assertTrue($vehicle->isRental());
    }

    public function test_rental_requires_provider_and_dates(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->from(route('vehicles.create'))
            ->post(route('vehicles.store'), [
                'license_plate' => 'M-RT 2',
                'vehicle_type' => VehicleType::Car->value,
                'propulsion' => VehiclePropulsion::Petrol->value,
                'ownership' => VehicleOwnership::Rental->value,
            ])
            ->assertRedirect(route('vehicles.create'))
            ->assertSessionHasErrors(['rental_provider', 'rental_start', 'rental_end']);
    }

    public function test_tour_save_rejects_rental_outside_period(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $vehicle = Vehicle::factory()->rental()->create([
            'organization_id' => $this->organization->id,
            'rental_start' => '2026-04-01',
            'rental_end' => '2026-04-30',
        ]);

        $this->actingAs($admin)
            ->from(route('tours.create'))
            ->post(route('tours.store'), [
                'user_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'tour_date' => '2026-05-15',
                'name' => 'Außerhalb',
            ])
            ->assertRedirect(route('tours.create'))
            ->assertSessionHasErrors(['vehicle_id']);
    }

    public function test_tour_save_accepts_rental_within_period(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $vehicle = Vehicle::factory()->rental()->create([
            'organization_id' => $this->organization->id,
            'rental_start' => '2026-05-01',
            'rental_end' => '2026-05-31',
        ]);

        $this->actingAs($admin)
            ->post(route('tours.store'), [
                'user_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'tour_date' => '2026-05-15',
                'name' => 'Mietwagentour',
            ])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_materialize_marks_rental_vehicle_legs(): void {
        $driver = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'home_lat' => 52.5,
            'home_lng' => 13.0,
            'home_address' => 'Home',
        ]);
        $vehicle = Vehicle::factory()->rental()->create([
            'organization_id' => $this->organization->id,
        ]);

        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-05-15'));
        $tour->vehicle_id = $vehicle->id;
        $tour->save();

        $order = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-05-15',
            'address_lat' => 52.5,
            'address_lng' => 13.1,
        ]);
        $service->assignOrders($tour, [$order->id]);

        $logs = $service->materializeToTravelLogs($tour->fresh());

        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $this->assertSame(TravelLogVehicle::Rental, $log->vehicle);
        }
    }

    public function test_is_available_on_respects_rental_window(): void {
        $vehicle = Vehicle::factory()->rental()->make([
            'rental_start' => '2026-05-01',
            'rental_end' => '2026-05-31',
        ]);

        $this->assertTrue($vehicle->isAvailableOn(CarbonImmutable::parse('2026-05-01')));
        $this->assertTrue($vehicle->isAvailableOn(CarbonImmutable::parse('2026-05-31')));
        $this->assertFalse($vehicle->isAvailableOn(CarbonImmutable::parse('2026-04-30')));
        $this->assertFalse($vehicle->isAvailableOn(CarbonImmutable::parse('2026-06-01')));
    }
}
