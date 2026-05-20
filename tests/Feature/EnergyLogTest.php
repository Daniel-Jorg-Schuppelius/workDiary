<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnergyLogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\EnergyLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Fleet\EnergyLogService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class EnergyLogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private User $admin;

    private Vehicle $vehicle;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_renders(): void {
        $this->actingAs($this->user);
        $this->get(route('energy-logs.index'))->assertOk()->assertSee(__('Tank- & Ladelog'));
    }

    public function test_store_creates_fuel_entry(): void {
        $this->actingAs($this->user);
        $start = CarbonImmutable::today()->setTime(10, 0);

        $this->post(route('energy-logs.store'), [
            'vehicle_id' => $this->vehicle->id,
            'energy_type' => EnergyLog::TYPE_FUEL,
            'fuel_kind' => EnergyLog::FUEL_DIESEL,
            'quantity' => '42.5',
            'cost_total' => '70.00',
            'odometer_km' => 50000,
            'started_at' => $start->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('energy-logs.index'));

        $log = EnergyLog::query()->firstOrFail();
        $this->assertSame(EnergyLog::UNIT_LITER, $log->unit);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertEqualsWithDelta(70.0, (float) $log->cost_total, 0.01);
    }

    public function test_electric_entry_forces_kwh_unit_and_clears_fuel_kind(): void {
        $this->actingAs($this->user);
        $start = CarbonImmutable::today()->setTime(11, 0);

        $this->post(route('energy-logs.store'), [
            'vehicle_id' => $this->vehicle->id,
            'energy_type' => EnergyLog::TYPE_ELECTRIC,
            'fuel_kind' => EnergyLog::FUEL_DIESEL, // should be discarded by model hook
            'quantity' => '32',
            'cost_total' => '12.50',
            'started_at' => $start->format('Y-m-d\TH:i'),
            'soc_before' => 20,
            'soc_after' => 80,
            'charger_type' => EnergyLog::CHARGER_DC_FAST,
        ])->assertRedirect();

        $log = EnergyLog::query()->firstOrFail();
        $this->assertSame(EnergyLog::UNIT_KWH, $log->unit);
        $this->assertNull($log->fuel_kind);
        $this->assertSame(EnergyLog::CHARGER_DC_FAST, $log->charger_type);
    }

    public function test_distance_since_last_computed_from_previous_odometer(): void {
        $service = app(EnergyLogService::class);
        $start = CarbonImmutable::today()->setTime(9, 0);

        $service->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->user->id,
            'energy_type' => EnergyLog::TYPE_FUEL,
            'fuel_kind' => EnergyLog::FUEL_DIESEL,
            'quantity' => '40',
            'odometer_km' => 80000,
            'started_at' => $start,
        ]);

        $second = $service->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->user->id,
            'energy_type' => EnergyLog::TYPE_FUEL,
            'fuel_kind' => EnergyLog::FUEL_DIESEL,
            'quantity' => '38',
            'odometer_km' => 80512,
            'started_at' => $start->addDays(7),
        ]);

        $this->assertSame(512, $second->fresh()->distance_since_last);
    }

    public function test_user_cannot_edit_others_entry(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $log = EnergyLog::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $other->id,
        ]);

        $this->actingAs($this->user);
        $this->get(route('energy-logs.edit', $log))->assertForbidden();
    }

    public function test_non_admin_cannot_view_other_users_logs(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->user);
        $this->get(route('energy-logs.index', ['user' => $other->id]))->assertForbidden();
    }

    public function test_admin_can_view_all_users_logs(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        EnergyLog::factory()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $other->id,
            'started_at' => CarbonImmutable::today(),
            'ended_at' => CarbonImmutable::today()->addMinutes(5),
        ]);

        $this->actingAs($this->admin);
        $this->get(route('energy-logs.index', ['user' => 'all']))->assertOk();
    }
}
