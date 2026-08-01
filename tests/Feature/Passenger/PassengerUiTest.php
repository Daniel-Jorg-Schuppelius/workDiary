<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Passenger;

use App\Enums\Passenger\{RideOperationMode, RideStatus};
use App\Models\Passenger\{PassengerConcession, PassengerFareTariff, PassengerRide, PassengerShiftSettlement, PassengerVehicleProfile};
use App\Models\{Qualification, User, Vehicle};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Passenger\PassengerRideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-456 — Taxi/Mietwagen-UI: Profil-Gate (404), Modul-Gate (423),
 * Rechte (403), Fahrt-Lebenszyklus über die Weboberfläche, Stammdaten-
 * und Schichtabrechnungs-Dialoge sowie Tenant-Grenzen.
 */
class PassengerUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
    }

    private function activateProfile(): void {
        $settings = is_array($this->organization->settings) ? $this->organization->settings : [];
        $settings['branch_profile_code'] = PassengerRideService::PROFILE_CODE;
        $settings['branch_profile_versions'] = [PassengerRideService::PROFILE_CODE => 1];
        $this->organization->forceFill(['settings' => $settings])->save();
        $this->admin->unsetRelation('organization');
    }

    private function vehicle(): Vehicle {
        return Vehicle::query()->create([
            'organization_id' => $this->organization->id,
            'license_plate' => 'B-TX 9000',
            'vehicle_type' => 'car',
            'propulsion' => 'hybrid',
            'ownership' => 'owned',
        ]);
    }

    private function dispatchReady(User $driver, Vehicle $vehicle): void {
        $qualification = Qualification::query()->create([
            'organization_id' => $this->organization->id,
            'name' => PassengerRideService::DRIVER_QUALIFICATION,
            'is_active' => true,
        ]);
        $driver->qualifications()->attach($qualification->id, [
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
        ]);
        PassengerConcession::query()->create([
            'organization_id' => $this->organization->id,
            'operation_mode' => RideOperationMode::Taxi,
            'authority' => 'LABO Berlin',
            'reference_no' => 'TX-UI-1',
            'tariff_area' => 'Berlin',
            'valid_from' => now()->subYear()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'active' => true,
        ]);
        PassengerVehicleProfile::query()->create([
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'operation_modes' => [RideOperationMode::Taxi->value],
            'passenger_seats' => 4,
        ]);
    }

    public function test_profile_module_and_permission_gates(): void {
        // Ohne Branchenprofil → 404 (Existenz nicht verraten).
        $this->get(route('passenger-rides.index'))->assertNotFound();
        $this->get(route('passenger-masterdata.index'))->assertNotFound();
        $this->get(route('passenger-settlements.index'))->assertNotFound();

        $this->activateProfile();
        $this->get(route('passenger-rides.index'))->assertOk();
        $this->get(route('passenger-masterdata.index'))->assertOk();
        $this->get(route('passenger-settlements.index'))->assertOk();

        // Modul-Gate: ohne module.fuhrpark → 423.
        config(['license.feature_overrides' => ['module.fuhrpark' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->get(route('passenger-rides.index'))->assertStatus(423);
        config(['license.feature_overrides' => []]);
        app(FeatureFlagResolver::class)->flush();

        // Rechte: normales Mitglied ohne passenger.*-Permission → 403.
        $member = $this->orgUser();
        $this->actingAs($member)->get(route('passenger-rides.index'))->assertForbidden();
    }

    public function test_ride_lifecycle_via_web_ui(): void {
        $this->activateProfile();
        $driver = $this->orgUser();
        $vehicle = $this->vehicle();
        $this->dispatchReady($driver, $vehicle);

        // Annahme über den Dialog.
        $this->get(route('passenger-rides.create'))->assertOk();
        $response = $this->post(route('passenger-rides.store'), [
            'operation_mode' => RideOperationMode::Taxi->value,
            'order_channel' => 'phone',
            'pickup_address' => 'Alexanderplatz 1, Berlin',
            'destination_open' => '1',
            'passenger_count' => 2,
        ]);
        $ride = PassengerRide::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('passenger-rides.show', $ride));
        $this->assertSame(RideStatus::Accepted, $ride->status);

        // Disposition mit Sqids.
        $this->post(route('passenger-rides.assign', $ride), [
            'driver_user_id' => $driver->sqid,
            'vehicle_id' => $vehicle->sqid,
        ])->assertRedirect(route('passenger-rides.show', $ride));
        $this->assertSame(RideStatus::Assigned, $ride->refresh()->status);

        // Fahrtbeginn mit Tarif.
        $tariff = PassengerFareTariff::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Tag Berlin',
            'operation_mode' => RideOperationMode::Taxi,
            'valid_from' => now()->subMonth()->toDateString(),
            'base_price' => '4.3000',
            'price_per_km' => '2.6000',
            'price_per_minute' => '0.6000',
            'active' => true,
        ]);
        $this->post(route('passenger-rides.start', $ride), [
            'price_kind' => 'tariff',
            'tariff_id' => $tariff->sqid,
            'estimated_km' => '10',
            'estimated_minutes' => 20,
        ])->assertRedirect(route('passenger-rides.show', $ride));
        $ride->refresh();
        $this->assertSame(RideStatus::EnRoutePickup, $ride->status);
        $this->assertNotNull($ride->fare_snapshot);

        // Fahrgast aufnehmen und abschließen.
        $this->post(route('passenger-rides.transition', $ride), ['status' => RideStatus::Occupied->value])->assertRedirect();
        $this->post(route('passenger-rides.complete', $ride), [
            'meter_net' => '42.50',
            'tax_rate' => '7',
            'payment_method' => 'karte',
            'occupied_km' => '10.4',
        ])->assertRedirect(route('passenger-rides.show', $ride));
        $this->assertSame(RideStatus::Completed, $ride->refresh()->status);

        // Detailseite rendert Fahrer + Statuslabel.
        $this->get(route('passenger-rides.show', $ride))
            ->assertOk()
            ->assertSee($driver->name)
            ->assertSee($ride->status->label());
    }

    public function test_masterdata_dialogs_and_rule_management(): void {
        $this->activateProfile();

        $this->get(route('passenger-masterdata.tariffs.create'))->assertOk();
        $this->post(route('passenger-masterdata.tariffs.store'), [
            'name' => 'Nacht Berlin',
            'tariff_area' => 'Berlin',
            'operation_mode' => RideOperationMode::Taxi->value,
            'valid_from' => now()->toDateString(),
            'base_price' => '4.80',
            'price_per_km' => '2.90',
            'price_per_minute' => '0.65',
            'active' => '1',
        ])->assertRedirect(route('passenger-masterdata.index'));
        $tariff = PassengerFareTariff::query()->where('name', 'Nacht Berlin')->firstOrFail();

        // Zuschlagsregel inline anlegen und entfernen.
        $this->post(route('passenger-masterdata.tariffs.rules.store', $tariff), [
            'code' => 'gepaeck',
            'label' => 'Gepäckzuschlag',
            'kind' => 'surcharge',
            'amount' => '1.00',
        ])->assertRedirect();
        $rule = $tariff->rules()->firstOrFail();
        $this->get(route('passenger-masterdata.index', ['tariff' => $tariff->sqid]))
            ->assertOk()->assertSee('Gepäckzuschlag');
        $this->delete(route('passenger-masterdata.tariffs.rules.destroy', [$tariff, $rule]))->assertRedirect();
        $this->assertSame(0, $tariff->rules()->count());

        // Konzession + Fahrzeugprofil über die Dialoge.
        $this->post(route('passenger-masterdata.concessions.store'), [
            'operation_mode' => RideOperationMode::RentalCar->value,
            'authority' => 'LABO Berlin',
            'reference_no' => 'MW-77',
            'active' => '1',
        ])->assertRedirect(route('passenger-masterdata.index'));
        $this->assertTrue(PassengerConcession::query()->where('reference_no', 'MW-77')->exists());

        $vehicle = $this->vehicle();
        $this->post(route('passenger-masterdata.vehicle-profiles.store'), [
            'vehicle_id' => $vehicle->sqid,
            'operation_modes' => [RideOperationMode::Taxi->value, RideOperationMode::RentalCar->value],
            'passenger_seats' => 6,
            'meter_kind' => 'taxameter',
        ])->assertRedirect(route('passenger-masterdata.index'));
        $this->assertTrue(PassengerVehicleProfile::query()->where('vehicle_id', $vehicle->id)->exists());
    }

    public function test_settlement_close_requires_reason_for_open_difference(): void {
        $this->activateProfile();
        $driver = $this->orgUser();

        $this->post(route('passenger-settlements.store'), [
            'driver_user_id' => $driver->sqid,
            'shift_date' => now()->toDateString(),
            'meter_total' => '500.00',
            'cash_total' => '480.00',
        ])->assertRedirect(route('passenger-settlements.index'));
        $settlement = PassengerShiftSettlement::query()->firstOrFail();
        $this->assertSame('20.00', $settlement->computeDifference());

        // Offene Differenz ohne Begründung → Validierungsfehler, bleibt offen.
        $this->post(route('passenger-settlements.close', $settlement))
            ->assertSessionHasErrors('difference_reason');
        $this->assertSame(PassengerShiftSettlement::STATUS_OPEN, $settlement->refresh()->status);

        $this->post(route('passenger-settlements.close', $settlement), [
            'difference_reason' => 'Kartenterminal-Abrechnung folgt am Montag.',
        ])->assertRedirect(route('passenger-settlements.index'));
        $this->assertSame(PassengerShiftSettlement::STATUS_DISPUTED, $settlement->refresh()->status);
    }

    public function test_foreign_organization_cannot_access_rides(): void {
        $this->activateProfile();
        $service = app(PassengerRideService::class);
        $ride = $service->accept($this->organization, $this->admin, [
            'operation_mode' => RideOperationMode::Taxi->value,
            'order_channel' => 'phone',
            'pickup_address' => 'Teststraße 1',
            'destination_open' => true,
        ]);

        $foreignAdmin = User::factory()->admin()->create();
        $foreignOrg = $foreignAdmin->organization;
        $settings = is_array($foreignOrg->settings) ? $foreignOrg->settings : [];
        $settings['branch_profile_code'] = PassengerRideService::PROFILE_CODE;
        $foreignOrg->forceFill(['settings' => $settings])->save();
        $foreignAdmin->unsetRelation('organization');

        $this->actingAs($foreignAdmin)->get(route('passenger-rides.show', $ride))->assertNotFound();
    }
}
