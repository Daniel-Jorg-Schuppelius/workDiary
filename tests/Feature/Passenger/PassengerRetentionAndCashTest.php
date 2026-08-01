<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerRetentionAndCashTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Passenger;

use App\Enums\Passenger\{RideOperationMode, RideStatus};
use App\Models\{CashEntry, CashRegister, User};
use App\Models\Passenger\{PassengerRide, PassengerShiftSettlement};
use App\Models\Privacy\RetentionProposal;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Passenger\PassengerRideService;
use App\Services\Privacy\Retention\{RetentionRegistry, RetentionScanService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-456-Lückenschluss (Issue #74): Retention der Fahrtakten (Orts-/
 * Fahrgastbezug wird nach Frist anonymisiert, kaufmännische Werte bleiben)
 * und Kassenbuch-Übergabe der Schichtabrechnung (genau eine Buchung,
 * rückverlinkt, nur nach Abschluss).
 */
class PassengerRetentionAndCashTest extends TestCase {
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

    private function oldCompletedRide(): PassengerRide {
        $ride = app(PassengerRideService::class)->accept($this->organization, $this->admin, [
            'operation_mode' => RideOperationMode::Taxi->value,
            'order_channel' => 'phone',
            'pickup_address' => 'Alexanderplatz 1, Berlin',
            'destination_address' => 'Flughafen BER',
            'passenger_name' => 'Erika Musterfrau',
            'passenger_contact' => '+49 30 1234567',
        ]);
        $ride->forceFill([
            'status' => RideStatus::Completed,
            'completed_at' => now()->subYears(2),
            'meter_net' => '42.50',
            'gross_amount' => '45.48',
        ])->save();

        return $ride->refresh();
    }

    public function test_retention_area_is_registered_with_period(): void {
        $registry = app(RetentionRegistry::class);

        $this->assertNotNull($registry->policy('passenger_rides'), 'Retention-Policy passenger_rides fehlt.');
        $this->assertSame(1, $registry->yearsFor($this->organization, 'passenger_rides'));
        $this->assertNotNull($registry->basisFor($this->organization, 'passenger_rides'));
    }

    public function test_scan_proposes_and_purge_anonymizes_ride_pii_only(): void {
        $ride = $this->oldCompletedRide();
        // Frische Fahrt als Gegenprobe: darf NICHT vorgeschlagen werden.
        $fresh = app(PassengerRideService::class)->accept($this->organization, $this->admin, [
            'operation_mode' => RideOperationMode::Taxi->value,
            'order_channel' => 'phone',
            'pickup_address' => 'Kurfürstendamm 1, Berlin',
            'destination_address' => 'Potsdamer Platz 1, Berlin',
        ]);
        $fresh->forceFill(['status' => RideStatus::Completed, 'completed_at' => now()->subDay()])->save();

        $service = app(RetentionScanService::class);
        $service->scan($this->organization);

        $proposals = RetentionProposal::query()->where('area', 'passenger_rides')->get();
        $this->assertCount(1, $proposals);
        $proposal = $proposals->firstOrFail();
        $this->assertSame((string) $ride->id, (string) $proposal->subject_id);

        // Scan löscht nichts — erst approve → purge anonymisiert.
        $this->assertSame('Erika Musterfrau', $ride->refresh()->passenger_name);

        $service->approve($proposal, $this->admin);
        $service->purge($proposal->refresh(), $this->admin);

        $ride->refresh();
        $this->assertNull($ride->passenger_name);
        $this->assertNull($ride->passenger_contact);
        $this->assertNull($ride->pickup_address);
        $this->assertNull($ride->destination_address);
        $this->assertNotNull($ride->anonymized_at);
        // Kaufmännische Werte bleiben als Nachweis.
        $this->assertSame('42.50', $ride->meter_net);
        $this->assertSame('45.48', $ride->gross_amount);

        // Idempotenz: anonymisierte Fahrt wird nicht erneut vorgeschlagen.
        $service->scan($this->organization);
        $this->assertSame(0, RetentionProposal::query()->where('area', 'passenger_rides')->where('id', '!=', $proposal->id)->count());
    }

    public function test_cash_handover_posts_exactly_once_after_close(): void {
        $this->activateProfile();
        $driver = $this->orgUser();
        $register = CashRegister::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Hauptkasse',
            'currency' => 'EUR',
            'opening_balance' => '0.00',
            'opened_on' => now()->subYear()->toDateString(),
            'active' => true,
        ]);

        $settlement = PassengerShiftSettlement::query()->create([
            'organization_id' => $this->organization->id,
            'driver_user_id' => $driver->id,
            'shift_date' => now()->toDateString(),
            'meter_total' => '480.00',
            'cash_total' => '480.00',
        ]);

        // Offene Abrechnung darf nicht übergeben werden.
        $this->post(route('passenger-settlements.cash-entry', $settlement), [
            'cash_register_id' => $register->sqid,
        ])->assertSessionHasErrors('status');
        $this->assertNull($settlement->refresh()->cash_entry_id);

        $this->post(route('passenger-settlements.close', $settlement))->assertRedirect(route('passenger-settlements.index'));
        $this->assertSame(PassengerShiftSettlement::STATUS_BALANCED, $settlement->refresh()->status);

        $this->post(route('passenger-settlements.cash-entry', $settlement), [
            'cash_register_id' => $register->sqid,
        ])->assertRedirect(route('passenger-settlements.index'));

        $settlement->refresh();
        $this->assertNotNull($settlement->cash_entry_id);
        $entry = CashEntry::query()->findOrFail($settlement->cash_entry_id);
        $this->assertSame(CashEntry::DIRECTION_IN, $entry->direction);
        $this->assertSame(480.0, $entry->amount?->toFloat());
        $this->assertSame($register->id, $entry->cash_register_id);

        // Zweite Übergabe ist gesperrt (genau eine Buchung je Abrechnung).
        $this->post(route('passenger-settlements.cash-entry', $settlement), [
            'cash_register_id' => $register->sqid,
        ])->assertSessionHasErrors('status');
        $this->assertSame(1, CashEntry::query()->count());
    }

    public function test_cash_handover_requires_cash_module(): void {
        $this->activateProfile();
        $driver = $this->orgUser();
        $settlement = PassengerShiftSettlement::query()->create([
            'organization_id' => $this->organization->id,
            'driver_user_id' => $driver->id,
            'shift_date' => now()->toDateString(),
            'meter_total' => '100.00',
            'cash_total' => '100.00',
            'status' => PassengerShiftSettlement::STATUS_BALANCED,
        ]);

        config(['license.feature_overrides' => ['module.kasse' => false]]);
        app(FeatureFlagResolver::class)->flush();

        $this->post(route('passenger-settlements.cash-entry', $settlement), [
            'cash_register_id' => 'irrelevant',
        ])->assertNotFound();

        config(['license.feature_overrides' => []]);
        app(FeatureFlagResolver::class)->flush();
    }
}
