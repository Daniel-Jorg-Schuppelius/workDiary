<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivateUseComparisonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Enums\Vehicle\VehiclePropulsion;
use App\Models\Asset\EnergyLog;
use App\Models\Fleet\{Vehicle, VehicleAnnualCost};
use App\Models\Platform\User;
use App\Models\Travel\TravelLog;
use App\Services\Fleet\PrivateUseComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-993: Jahresvergleich Fahrtenbuchmethode ↔ 1-%-Regel je Fahrzeug. */
final class PrivateUseComparisonTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function vehicle(VehiclePropulsion $propulsion, string $listPrice, ?int $commute = 20): Vehicle {
        return Vehicle::factory()->create([
            'organization_id' => $this->organization->id, 'logbook_mode' => true, 'propulsion' => $propulsion,
            'currency' => 'EUR', 'list_price_amount' => $listPrice, 'commute_distance_km' => $commute,
        ]);
    }

    private function trip(Vehicle $vehicle, string $date, TripKind $kind, int $start, int $end): void {
        TravelLog::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'vehicle_id' => $vehicle->id,
            'vehicle' => TravelLogVehicle::Company->value, 'trip_kind' => $kind, 'date' => $date,
            'distance_km' => $end - $start, 'odometer_start_km' => $start, 'odometer_end_km' => $end, 'purpose' => 'Fahrt',
        ]);
    }

    public function test_logbook_method_and_one_percent_rule_are_compared(): void {
        $vehicle = $this->vehicle(VehiclePropulsion::Petrol, '45678.00');
        $this->trip($vehicle, '2029-03-02', TripKind::Business, 10000, 11000);
        $this->trip($vehicle, '2029-04-05', TripKind::Private_, 11000, 11500);
        $this->trip($vehicle, '2029-05-06', TripKind::Commute, 11500, 12000);
        EnergyLog::factory()->create(['organization_id' => $this->organization->id, 'vehicle_id' => $vehicle->id, 'user_id' => $this->admin->id, 'cost_total' => '600.00', 'started_at' => '2029-04-10 10:00:00']);
        EnergyLog::factory()->create(['organization_id' => $this->organization->id, 'vehicle_id' => $vehicle->id, 'user_id' => $this->admin->id, 'cost_total' => '999.00', 'started_at' => '2028-12-10 10:00:00']);
        VehicleAnnualCost::query()->create(['organization_id' => $this->organization->id, 'vehicle_id' => $vehicle->id, 'year' => 2029, 'currency' => 'EUR', 'cost_amount' => '2400.00']);

        $result = app(PrivateUseComparison::class)->compare($vehicle, 2029);

        $this->assertSame(3, $result['months']);
        $this->assertSame([2000, 500, 500], [$result['km_total'], $result['km_private'], $result['km_commute']]);
        $this->assertSame('3000.00', $result['total_costs']->getAmount());
        $this->assertSame('1500.00', $result['logbook']->getAmount());
        // 45 600 × 1 % × 3 + 45 600 × 0,03 % × 20 × 3
        $this->assertSame('2188.80', $result['one_percent']?->getAmount());
        $this->assertSame('logbook', $result['cheaper']);
    }

    public function test_reduced_basis_follows_the_acquisition_date_and_the_charging_flag(): void {
        $comparison = app(PrivateUseComparison::class);
        $electric = $this->vehicle(VehiclePropulsion::Electric, '65000.00', null);
        $this->trip($electric, '2024-06-01', TripKind::Private_, 100, 200);

        $electric->update(['acquired_on' => '2024-03-01']);
        $this->assertSame('0.25', $comparison->compare($electric, 2024)['factor'], 'bis 70 000 € ab 2024');
        $this->assertSame('162.50', $comparison->compare($electric, 2024)['one_percent']?->getAmount());
        $electric->update(['acquired_on' => '2023-05-01']);
        $this->assertSame('0.5', $comparison->compare($electric, 2024)['factor'], 'Grenze der Anschaffung 2023: 60 000 €');
        $electric->update(['acquired_on' => '2018-06-01']);
        $this->assertSame('1', $comparison->compare($electric, 2024)['factor'], 'vor 2019 keine Minderung');

        $hybrid = $this->vehicle(VehiclePropulsion::Hybrid, '50000.00', null);
        $hybrid->update(['acquired_on' => '2022-01-10']);
        $this->assertSame('1', $comparison->compare($hybrid, 2024)['factor']);
        $hybrid->update(['is_externally_chargeable' => true]);
        $this->assertSame('0.5', $comparison->compare($hybrid, 2024)['factor']);

        $this->assertNull($comparison->compare($this->vehicle(VehiclePropulsion::Diesel, '0'), 2024)['one_percent']);
    }

    public function test_report_page_and_annual_cost_dialog_work(): void {
        $vehicle = $this->vehicle(VehiclePropulsion::Diesel, '30000.00');
        $this->actingAs($this->admin)->get(route('reports.logbook-comparison', ['year' => 2029]))->assertOk()->assertSee($vehicle->license_plate);
        $this->actingAs($this->admin)->get(route('reports.logbook-comparison.costs-form', ['vehicle' => $vehicle, 'year' => 2029]))->assertOk();
        $this->actingAs($this->admin)->post(route('reports.logbook-comparison.costs', $vehicle), ['year' => 2029, 'cost_amount' => '1234.50'])
            ->assertRedirect(route('reports.logbook-comparison', ['year' => 2029]));
        $this->assertSame('1234.50', VehicleAnnualCost::query()->sole()->cost_amount->getAmount());

        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)->post(route('reports.logbook-comparison.costs', $vehicle), ['year' => 2029, 'cost_amount' => '1'])->assertForbidden();
    }
}
