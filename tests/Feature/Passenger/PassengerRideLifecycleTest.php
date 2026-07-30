<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerRideLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Passenger;

use App\Enums\Passenger\{RideOperationMode, RideStatus};
use App\Models\Passenger\{PassengerConcession, PassengerFareTariff, PassengerRide, PassengerVehicleProfile};
use App\Models\{Qualification, User, Vehicle};
use App\Services\Passenger\PassengerRideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-456 — Fahrtlebenszyklus: eingefrorene Betriebsart, Pflichtgates
 * (Annahme/Disposition/Beginn/Abschluss), Tarif-/Preis-Snapshot getrennt vom
 * Gerätewert, Mietwagen-Nachweise und Tenant-Grenzen.
 */
class PassengerRideLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $dispatcher;

    private User $driver;

    private Vehicle $vehicle;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->dispatcher = $this->orgAdmin();
        $this->driver = $this->orgUser();
        $this->vehicle = Vehicle::query()->create([
            'organization_id' => $this->organization->id,
            'license_plate' => 'B-TX 1234',
            'vehicle_type' => 'car',
            'propulsion' => 'hybrid',
            'ownership' => 'owned',
        ]);
    }

    private function service(): PassengerRideService {
        return app(PassengerRideService::class);
    }

    private function qualifyDriver(?string $validUntil = null): void {
        $qualification = Qualification::query()->create([
            'organization_id' => $this->organization->id,
            'name' => PassengerRideService::DRIVER_QUALIFICATION,
            'is_active' => true,
        ]);
        $this->driver->qualifications()->attach($qualification->id, [
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => $validUntil ?? now()->addYear()->toDateString(),
        ]);
    }

    private function concession(RideOperationMode $mode = RideOperationMode::Taxi): PassengerConcession {
        return PassengerConcession::query()->create([
            'organization_id' => $this->organization->id,
            'operation_mode' => $mode,
            'authority' => 'LABO Berlin',
            'reference_no' => 'TX-' . $mode->value,
            'tariff_area' => 'Berlin',
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'active' => true,
        ]);
    }

    private function vehicleProfile(array $overrides = []): PassengerVehicleProfile {
        return PassengerVehicleProfile::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $this->vehicle->id,
            'order_number' => '1234',
            'operation_modes' => [RideOperationMode::Taxi->value, RideOperationMode::RentalCar->value],
            'passenger_seats' => 4,
            'meter_kind' => PassengerVehicleProfile::METER_TAXAMETER,
            'meter_serial' => 'TAX-1',
            'meter_calibrated_until' => now()->addYear()->toDateString(),
            'bokraft_checked_until' => now()->addYear()->toDateString(),
            'hu_valid_until' => now()->addYear()->toDateString(),
        ], $overrides));
    }

    private function acceptTaxiRide(array $overrides = []): PassengerRide {
        return $this->service()->accept($this->organization, $this->dispatcher, array_merge([
            'operation_mode' => RideOperationMode::Taxi->value,
            'order_channel' => 'phone',
            'pickup_address' => 'Alexanderplatz 1, Berlin',
            'destination_address' => 'Flughafen BER',
            'passenger_count' => 2,
        ], $overrides));
    }

    private function tariff(): PassengerFareTariff {
        return PassengerFareTariff::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Taxitarif Berlin 2026',
            'tariff_area' => 'Berlin',
            'operation_mode' => RideOperationMode::Taxi,
            'valid_from' => now()->subMonth()->toDateString(),
            'base_price' => '4.3000',
            'price_per_km' => '2.8000',
            'price_per_minute' => '0.5500',
            'fixed_price_min_percent' => '80.000',
            'fixed_price_max_percent' => '120.000',
            'currency' => 'EUR',
            'active' => true,
        ]);
    }

    public function test_accept_creates_ride_with_diary_anchor_and_frozen_mode(): void {
        $ride = $this->acceptTaxiRide();

        $this->assertSame(RideStatus::Accepted, $ride->status);
        $this->assertSame(RideOperationMode::Taxi, $ride->operation_mode);
        $this->assertNotNull($ride->diary_entry_id);
        $this->assertSame('Alexanderplatz 1, Berlin', $ride->pickup_address);
        // Taxi per Telefon: kein Betriebssitz-Nachweis nötig.
        $this->assertNull($ride->order_received_at);
    }

    public function test_rental_car_requires_office_receipt_channel(): void {
        $this->expectException(ValidationException::class);

        $this->acceptTaxiRide([
            'operation_mode' => RideOperationMode::RentalCar->value,
            'order_channel' => 'hail',
        ]);
    }

    public function test_rental_car_records_order_receipt(): void {
        $ride = $this->acceptTaxiRide([
            'operation_mode' => RideOperationMode::RentalCar->value,
            'order_channel' => 'phone',
            'order_receipt_reference' => 'Fax 2026-0815',
        ]);

        $this->assertNotNull($ride->order_received_at);
        $this->assertSame('Fax 2026-0815', $ride->order_receipt_reference);
    }

    public function test_dispatch_blocks_unqualified_driver_missing_concession_and_wrong_vehicle(): void {
        $ride = $this->acceptTaxiRide();

        // Ohne alles: drei harte Hindernisse.
        $issues = $this->service()->dispatchIssues($ride, $this->driver, $this->vehicle);
        $this->assertContains('passenger.issue.driver_unqualified', $issues);
        $this->assertContains('passenger.issue.concession_missing', $issues);
        $this->assertContains('passenger.issue.vehicle_profile_missing', $issues);

        // Abgelaufene Qualifikation zählt nicht.
        $this->qualifyDriver(now()->subDay()->toDateString());
        $issues = $this->service()->dispatchIssues($ride, $this->driver, $this->vehicle);
        $this->assertContains('passenger.issue.driver_unqualified', $issues);

        $this->expectException(ValidationException::class);
        $this->service()->assign($ride, $this->driver, $this->vehicle, $this->dispatcher);
    }

    public function test_dispatch_snapshot_and_start_freeze_tariff(): void {
        $this->qualifyDriver();
        $this->concession();
        $this->vehicleProfile();
        $tariff = $this->tariff();
        $ride = $this->acceptTaxiRide();

        $ride = $this->service()->assign($ride, $this->driver, $this->vehicle, $this->dispatcher);
        $this->assertSame(RideStatus::Assigned, $ride->status);
        $this->assertSame('B-TX 1234', $ride->assignment_snapshot['vehicle']['license_plate']);
        $this->assertSame('LABO Berlin', $ride->assignment_snapshot['concession']['authority']);

        $ride = $this->service()->start($ride, [
            'price_kind' => 'tariff',
            'tariff' => $tariff,
            'estimated_km' => '20',
            'estimated_minutes' => 30,
        ], $this->dispatcher);

        $this->assertSame(RideStatus::EnRoutePickup, $ride->status);
        $this->assertSame('Taxitarif Berlin 2026', $ride->fare_snapshot['name']);
        // 4.30 + 20×2.80 + 30×0.55 = 76.80
        $this->assertSame('76.80', (string) $ride->planned_net);

        // Snapshot bleibt eingefroren, auch wenn der Tarif sich ändert.
        $tariff->update(['base_price' => '9.9900']);
        $this->assertSame('4.3000', $ride->refresh()->fare_snapshot['base_price']);
    }

    public function test_taxi_tariff_is_mandatory_and_fixed_price_corridor_enforced(): void {
        $this->qualifyDriver();
        $this->concession();
        $this->vehicleProfile();
        $tariff = $this->tariff();

        // Ohne Tarif: blockiert.
        $ride = $this->acceptTaxiRide();
        $ride = $this->service()->assign($ride, $this->driver, $this->vehicle, $this->dispatcher);
        try {
            $this->service()->start($ride, ['price_kind' => 'tariff'], $this->dispatcher);
            $this->fail('Tarifpflicht wurde nicht durchgesetzt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tariff', $e->errors());
        }

        // Festpreis außerhalb des Korridors (Tarifpreis 76.80, 50 % davon): blockiert.
        try {
            $this->service()->start($ride, [
                'price_kind' => 'fixed_price',
                'tariff' => $tariff,
                'estimated_km' => '20',
                'estimated_minutes' => 30,
                'planned_net' => '38.40',
            ], $this->dispatcher);
            $this->fail('Festpreiskorridor wurde nicht durchgesetzt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('planned_net', $e->errors());
        }

        // Innerhalb des Korridors (100 %): erlaubt.
        $ride = $this->service()->start($ride, [
            'price_kind' => 'fixed_price',
            'tariff' => $tariff,
            'estimated_km' => '20',
            'estimated_minutes' => 30,
            'planned_net' => '76.80',
        ], $this->dispatcher);
        $this->assertSame(RideStatus::EnRoutePickup, $ride->status);
    }

    public function test_completion_requires_meter_tax_and_payment_and_keeps_deviation_visible(): void {
        $this->qualifyDriver();
        $this->concession();
        $this->vehicleProfile();
        $ride = $this->acceptTaxiRide();
        $ride = $this->service()->assign($ride, $this->driver, $this->vehicle, $this->dispatcher);
        $ride = $this->service()->start($ride, [
            'price_kind' => 'tariff',
            'tariff' => $this->tariff(),
            'estimated_km' => '20',
            'estimated_minutes' => 30,
        ], $this->dispatcher);
        $ride = $this->service()->transition($ride, RideStatus::Waiting, $this->dispatcher);
        $ride = $this->service()->transition($ride, RideStatus::Occupied, $this->dispatcher);

        // Ohne Gerätewert: blockiert.
        try {
            $this->service()->complete($ride, ['tax_rate' => '7', 'payment_method' => 'cash'], $this->dispatcher);
            $this->fail('Gerätewert-Pflicht wurde nicht durchgesetzt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('meter_net', $e->errors());
        }

        $ride = $this->service()->complete($ride, [
            'meter_net' => '80.00',
            'tax_rate' => '7',
            'payment_method' => 'cash',
            'occupied_km' => '21.4',
        ], $this->dispatcher);

        $this->assertSame(RideStatus::Completed, $ride->status);
        $this->assertSame('5.60', (string) $ride->tax_amount);
        $this->assertSame('85.60', (string) $ride->gross_amount);
        // Geplant 76.80 vs. Gerät 80.00 → Abweichung sichtbar.
        $this->assertTrue($ride->hasFareDeviation());
        $this->assertSame('3.20', $ride->fareDeviation());
    }

    public function test_status_machine_rejects_backward_and_skip_transitions(): void {
        $ride = $this->acceptTaxiRide();

        $this->expectException(ValidationException::class);
        $this->service()->transition($ride, RideStatus::Occupied, $this->dispatcher);
    }

    public function test_rental_car_return_proof_and_follow_up(): void {
        $this->qualifyDriver();
        $this->concession(RideOperationMode::RentalCar);
        $this->vehicleProfile();
        $ride = $this->acceptTaxiRide([
            'operation_mode' => RideOperationMode::RentalCar->value,
            'order_channel' => 'phone',
        ]);
        $ride = $this->service()->assign($ride, $this->driver, $this->vehicle, $this->dispatcher);
        $ride = $this->service()->start($ride, ['price_kind' => 'contract', 'planned_net' => '50.00'], $this->dispatcher);
        $ride = $this->service()->transition($ride, RideStatus::Occupied, $this->dispatcher);
        $ride = $this->service()->complete($ride, [
            'meter_net' => '50.00',
            'tax_rate' => '19',
            'payment_method' => 'invoice',
        ], $this->dispatcher);

        $this->assertTrue($ride->awaitsReturnProof());
        $ride = $this->service()->recordReturn($ride, $this->dispatcher);
        $this->assertFalse($ride->refresh()->awaitsReturnProof());
        $this->assertNotNull($ride->returned_to_base_at);
    }

    public function test_foreign_tenant_data_is_isolated(): void {
        $ride = $this->acceptTaxiRide();

        $foreign = \App\Models\Organization::factory()->create();
        app()->instance('currentOrganization', $foreign);
        $this->assertSame(0, PassengerRide::query()->count());

        app()->instance('currentOrganization', $this->organization);
        $this->assertSame(1, PassengerRide::query()->count());
        $this->assertSame($ride->id, PassengerRide::query()->firstOrFail()->id);
    }
}
