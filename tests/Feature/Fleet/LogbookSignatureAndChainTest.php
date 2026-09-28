<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogbookSignatureAndChainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Models\Fleet\Vehicle;
use App\Models\Platform\User;
use App\Models\Travel\TravelLog;
use App\Services\Travel\TravelLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-992: Fahrer-Signatur und Neuberechnung nach einem Storno mitten in der Kette. */
final class LogbookSignatureAndChainTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private User $driver;

    private Vehicle $vehicle;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        Carbon::setTestNow('2030-06-20 12:00:00');
        $this->driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-FB 992', 'logbook_mode' => true, 'odometer_km' => null]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function trip(int $start, int $end, string $date, array $extra = []): TravelLog {
        return app(TravelLogService::class)->create(array_merge([
            'organization_id' => $this->organization->id, 'user_id' => $this->driver->id, 'vehicle_id' => $this->vehicle->id,
            'vehicle' => TravelLogVehicle::Company->value, 'trip_kind' => TripKind::Business->value, 'date' => $date,
            'distance_km' => $end - $start, 'odometer_start_km' => $start, 'odometer_end_km' => $end, 'to_address' => 'Kunde', 'purpose' => 'Termin',
        ], $extra));
    }

    public function test_only_the_driver_signs_once_and_signing_locks_the_trip(): void {
        $trip = $this->trip(1000, 1050, '2030-06-20');
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)->get(route('travel-logs.sign-form', $trip))->assertForbidden();
        $this->actingAs($this->driver)->get(route('travel-logs.sign-form', $trip))->assertOk()->assertSee('signaturePad', false);
        $this->actingAs($this->driver)->post(route('travel-logs.sign', $trip), ['signature' => self::PNG])->assertRedirect();

        $trip->refresh();
        $this->assertTrue($trip->isSigned());
        $this->assertTrue($trip->isLocked());
        $this->assertSame(64, strlen((string) $trip->driver_signature_hash));
        Storage::disk('local')->assertExists((string) $trip->driver_signature_path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'travelLog.signed', 'auditable_id' => $trip->id]);

        $this->expectException(ValidationException::class);
        app(TravelLogService::class)->sign($trip, $this->driver, self::PNG);
    }

    public function test_a_trip_locked_at_day_end_can_still_be_signed_but_never_changed(): void {
        $trip = $this->trip(1000, 1050, '2030-06-18');
        app(TravelLogService::class)->lock($trip);

        app(TravelLogService::class)->sign($trip->refresh(), $this->driver, self::PNG);
        $this->assertTrue($trip->refresh()->isSigned());

        $this->expectException(\RuntimeException::class);
        $trip->forceFill(['driver_signature_hash' => 'manipuliert'])->save();
    }

    public function test_correcting_a_trip_mid_chain_repairs_the_next_trip_with_a_follow_up_correction(): void {
        $first = $this->trip(1000, 1200, '2030-06-17');
        $second = $this->trip(1200, 1260, '2030-06-18');
        $third = $this->trip(1260, 1300, '2030-06-19');
        $service = app(TravelLogService::class);
        foreach ([$first, $second, $third] as $trip) {
            $service->lock($trip->refresh());
        }

        // Tippfehler im ersten Stand: 1200 statt 1180.
        $correction = $service->correct($first->refresh(), [
            'vehicle_id' => $this->vehicle->id, 'vehicle' => TravelLogVehicle::Company->value, 'trip_kind' => TripKind::Business->value,
            'date' => '2030-06-17', 'distance_km' => 180, 'odometer_start_km' => 1000, 'odometer_end_km' => 1180, 'to_address' => 'Kunde', 'purpose' => 'Termin',
        ], 'Tachostand falsch abgelesen', $this->driver);

        $repair = TravelLog::query()->where('corrects_travel_log_id', $second->id)->sole();
        $this->assertSame(1180, (int) $repair->odometer_start_km);
        $this->assertSame(1260, (int) $repair->odometer_end_km);
        $this->assertStringContainsString('17.06.2030', (string) $repair->correction_reason);
        $this->assertSame(1200, (int) $second->refresh()->odometer_start_km, 'das Original bleibt unverändert');
        $this->assertSame(0, TravelLog::query()->where('corrects_travel_log_id', $third->id)->count(), 'die dritte Fahrt schließt schon an');
        $this->assertSame(1180, (int) $correction->odometer_end_km);

        $effective = TravelLog::query()->effective()->orderBy('odometer_start_km')->get(['odometer_start_km', 'odometer_end_km'])
            ->map(fn (TravelLog $t): string => $t->odometer_start_km . '-' . $t->odometer_end_km)->all();
        $this->assertSame(['1000-1180', '1180-1260', '1260-1300'], $effective);
    }

    public function test_an_open_successor_is_adjusted_directly(): void {
        $first = $this->trip(1000, 1200, '2030-06-20');
        app(TravelLogService::class)->lock($first);
        $second = $this->trip(1200, 1260, '2030-06-20');

        app(TravelLogService::class)->correct($first->refresh(), [
            'vehicle_id' => $this->vehicle->id, 'vehicle' => TravelLogVehicle::Company->value, 'trip_kind' => TripKind::Business->value,
            'date' => '2030-06-20', 'distance_km' => 190, 'odometer_start_km' => 1000, 'odometer_end_km' => 1190, 'to_address' => 'Kunde', 'purpose' => 'Termin',
        ], 'Korrektur', $this->driver);

        $this->assertSame(1190, (int) $second->refresh()->odometer_start_km);
        $this->assertSame(0, TravelLog::query()->where('corrects_travel_log_id', $second->id)->count());
    }
}
