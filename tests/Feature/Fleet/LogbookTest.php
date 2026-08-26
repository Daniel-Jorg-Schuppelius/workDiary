<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogbookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Fleet;

use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Exceptions\{LogbookViolationException, TravelLogLockedException};
use App\Models\{TravelLog, User, Vehicle};
use App\Services\Travel\TravelLogService;
use App\Support\Gobd\GobdLockRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Feature 137 (MVP-702): steuerliches Fahrtenbuch — Modus-Schalter je
 * Fahrzeug, Pflicht-km-Stände, lückenlose km-Kette (Blocker im Logbook-Modus,
 * Warnung sonst), Plausibilität ±5 %, Festschreibung (explizit + Tagesende)
 * mit Modell-Guard, Stornofahrt, Report-Summen/privater Anteil — und der
 * unveränderte Erstattungsmodus.
 */
class LogbookTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Carbon::setTestNow('2030-06-15 12:00:00');
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vehicle(bool $logbook = true): Vehicle {
        return Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'license_plate' => $logbook ? 'B-FB 137' : 'B-ER 100',
            'logbook_mode' => $logbook,
            'odometer_km' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Vehicle $vehicle, array $overrides = []): array {
        return array_merge([
            'date' => '2030-06-15',
            'vehicle' => TravelLogVehicle::Company->value,
            'vehicle_id' => $vehicle->sqid,
            'trip_kind' => TripKind::Business->value,
            'from_address' => 'Büro',
            'to_address' => 'Kunde',
            'purpose' => 'Kundentermin',
            'distance_km' => 50,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function postTrip(array $payload): TestResponse {
        return $this->actingAs($this->user)->from(route('travel-logs.index'))->post(route('travel-logs.store'), $payload);
    }

    /** @param array<string, mixed> $attributes */
    private function trip(Vehicle $vehicle, array $attributes = []): TravelLog {
        return app(TravelLogService::class)->create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'vehicle' => TravelLogVehicle::Company->value,
            'trip_kind' => TripKind::Business->value,
            'date' => '2030-06-15',
            'distance_km' => 50,
            'odometer_start_km' => 1000,
            'odometer_end_km' => 1050,
            'to_address' => 'Kunde',
            'purpose' => 'Termin',
        ], $attributes));
    }

    public function test_logbook_mode_switch_on_vehicle_form(): void {
        $this->actingAs($this->user)->post(route('vehicles.store'), [
            'license_plate' => 'B-FB 1',
            'vehicle_type' => 'car',
            'propulsion' => 'diesel',
            'logbook_mode' => '1',
        ])->assertRedirect(route('vehicles.index'));
        $this->assertTrue(Vehicle::query()->where('license_plate', 'B-FB 1')->firstOrFail()->logbook_mode);

        $this->actingAs($this->user)->post(route('vehicles.store'), [
            'license_plate' => 'B-FB 2',
            'vehicle_type' => 'car',
            'propulsion' => 'diesel',
        ])->assertRedirect(route('vehicles.index'));
        $this->assertFalse(Vehicle::query()->where('license_plate', 'B-FB 2')->firstOrFail()->logbook_mode);
    }

    public function test_logbook_requires_odometer_readings(): void {
        $vehicle = $this->vehicle();

        $this->postTrip($this->payload($vehicle))
            ->assertRedirect(route('travel-logs.index'))
            ->assertSessionHasErrors(['odometer_start_km', 'odometer_end_km']);
        $this->assertSame(0, TravelLog::query()->count());

        $this->postTrip($this->payload($vehicle, ['odometer_start_km' => 1000, 'odometer_end_km' => 1050]))
            ->assertSessionHasNoErrors();
        $log = TravelLog::query()->firstOrFail();
        $this->assertSame(1000, $log->odometer_start_km);
        $this->assertSame(1050, $log->odometer_end_km);
        $this->assertSame(TripKind::Business, $log->trip_kind);
        $this->assertNull($log->locked_at);
        // Tachostand des Fahrzeugs wird nachgezogen.
        $this->assertSame(1050, $vehicle->fresh()?->odometer_km);
    }

    public function test_odometer_end_must_not_be_below_start(): void {
        $vehicle = $this->vehicle();

        $this->postTrip($this->payload($vehicle, ['odometer_start_km' => 1050, 'odometer_end_km' => 1000]))
            ->assertSessionHasErrors('odometer_end_km');
    }

    public function test_plausibility_tolerates_five_percent(): void {
        $vehicle = $this->vehicle();

        // 50 km Tacho, 40 km erfasst → 20 % Abweichung.
        $this->postTrip($this->payload($vehicle, ['distance_km' => 40, 'odometer_start_km' => 1000, 'odometer_end_km' => 1050]))
            ->assertSessionHasErrors('distance_km');
        // 49 km erfasst → 2 %.
        $this->postTrip($this->payload($vehicle, ['distance_km' => 49, 'odometer_start_km' => 1000, 'odometer_end_km' => 1050]))
            ->assertSessionHasNoErrors();
        // Hin- und Rückfahrt: Distanz ist einfache Strecke, Tacho zählt beide Richtungen.
        $this->postTrip($this->payload($vehicle, ['date' => '2030-06-15', 'distance_km' => 25, 'round_trip' => '1', 'odometer_start_km' => 1050, 'odometer_end_km' => 1100]))
            ->assertSessionHasNoErrors();
    }

    public function test_km_chain_blocks_gaps_in_logbook_mode(): void {
        $vehicle = $this->vehicle();
        $this->trip($vehicle);

        $this->postTrip($this->payload($vehicle, ['odometer_start_km' => 1060, 'odometer_end_km' => 1110]))
            ->assertSessionHasErrors('odometer_start_km');
        $this->assertSame(1, TravelLog::query()->count());

        $this->postTrip($this->payload($vehicle, ['odometer_start_km' => 1050, 'odometer_end_km' => 1100]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, TravelLog::query()->count());
    }

    public function test_km_chain_gap_is_only_a_warning_outside_logbook_mode(): void {
        $vehicle = $this->vehicle(logbook: false);
        $this->trip($vehicle);

        $this->postTrip($this->payload($vehicle, ['odometer_start_km' => 1060, 'odometer_end_km' => 1110]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');
        $this->assertSame(2, TravelLog::query()->count());
    }

    public function test_explicit_lock_freezes_trip_and_guard_throws(): void {
        $vehicle = $this->vehicle();
        $log = $this->trip($vehicle);

        $this->actingAs($this->user)->post(route('travel-logs.lock', $log))->assertRedirect(route('travel-logs.index'));
        $log->refresh();
        $this->assertNotNull($log->locked_at);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => TravelLog::class, 'auditable_id' => $log->id, 'event' => 'travelLog.locked']);

        // Modell-Guard (GobdLockRegistry: freeze) — fachliche Felder gesperrt, Löschen gesperrt.
        $this->assertArrayHasKey('TravelLog', GobdLockRegistry::MODELS);
        try {
            $log->update(['purpose' => 'umgeschrieben']);
            $this->fail('Festgeschriebene Fahrt wurde geändert.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('unveränderlich', $e->getMessage());
        }
        try {
            $log->delete();
            $this->fail('Festgeschriebene Fahrt wurde gelöscht.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('nicht gelöscht', $e->getMessage());
        }
        $this->assertSame('Termin', $log->fresh()?->purpose);

        // HTTP-Wege: Bearbeiten/Aktualisieren/Löschen werden mit Meldung abgewiesen.
        $this->actingAs($this->user)->get(route('travel-logs.edit', $log))->assertRedirect(route('travel-logs.index'))->assertSessionHas('error');
        $this->actingAs($this->user)->put(route('travel-logs.update', $log), $this->payload($vehicle, ['purpose' => 'neu', 'odometer_start_km' => 1000, 'odometer_end_km' => 1050]))
            ->assertRedirect(route('travel-logs.index'))->assertSessionHas('error');
        $this->actingAs($this->user)->delete(route('travel-logs.destroy', $log))->assertRedirect(route('travel-logs.index'))->assertSessionHas('error');
        $this->assertDatabaseHas('travel_logs', ['id' => $log->id, 'purpose' => 'Termin']);
    }

    public function test_trip_of_past_day_is_locked_on_write_and_by_nightly_command(): void {
        $vehicle = $this->vehicle();
        $yesterday = $this->trip($vehicle, ['date' => '2030-06-14']);
        $today = $this->trip($vehicle, ['date' => '2030-06-15', 'odometer_start_km' => 1050, 'odometer_end_km' => 1100]);
        $reimbursement = $this->trip($this->vehicle(logbook: false), ['date' => '2030-06-10', 'odometer_start_km' => null, 'odometer_end_km' => null]);

        // Tagesende-Regel greift beim Schreibversuch auch ohne nächtlichen Lauf.
        try {
            app(TravelLogService::class)->update($yesterday, ['purpose' => 'spät geändert']);
            $this->fail('Fahrt des Vortags wurde geändert.');
        } catch (TravelLogLockedException) {
            // erwartet
        }
        $this->assertNotNull($yesterday->fresh()?->locked_at);

        // Nächtlicher Lauf: nur Logbook-Fahrten vergangener Tage.
        $yesterday2 = $this->trip($vehicle, ['date' => '2030-06-13', 'odometer_start_km' => 1100, 'odometer_end_km' => 1150]);
        $this->assertSame(0, Artisan::call('travel-logs:lock-due'));
        $this->assertNotNull($yesterday2->fresh()?->locked_at);
        $this->assertNull($today->fresh()?->locked_at);
        $this->assertNull($reimbursement->fresh()?->locked_at);
    }

    public function test_correction_trip_replaces_locked_original(): void {
        $vehicle = $this->vehicle();
        $original = $this->trip($vehicle);
        app(TravelLogService::class)->lock($original, $this->user);

        // Ohne Grund keine Stornofahrt.
        $this->postTrip($this->payload($vehicle, ['corrects_travel_log_id' => $original->sqid, 'odometer_start_km' => 1000, 'odometer_end_km' => 1048, 'distance_km' => 48]))
            ->assertSessionHasErrors('correction_reason');

        $this->postTrip($this->payload($vehicle, [
            'corrects_travel_log_id' => $original->sqid,
            'correction_reason' => 'Tachostand falsch abgelesen',
            'odometer_start_km' => 1000,
            'odometer_end_km' => 1048,
            'distance_km' => 48,
        ]))->assertSessionHasNoErrors();

        $correction = TravelLog::query()->where('corrects_travel_log_id', $original->id)->firstOrFail();
        $this->assertSame('Tachostand falsch abgelesen', $correction->correction_reason);
        $this->assertSame(1048, $correction->odometer_end_km);
        // Original bleibt unverändert und festgeschrieben.
        $this->assertSame(1050, $original->fresh()?->odometer_end_km);
        $this->assertNotNull($original->refresh()->locked_at);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => TravelLog::class, 'auditable_id' => $original->id, 'event' => 'travelLog.corrected']);

        // Kette folgt der Korrektur, nicht dem stornierten Original.
        $this->postTrip($this->payload($vehicle, ['odometer_start_km' => 1050, 'odometer_end_km' => 1100]))
            ->assertSessionHasErrors('odometer_start_km');
        $this->postTrip($this->payload($vehicle, ['odometer_start_km' => 1048, 'odometer_end_km' => 1098]))
            ->assertSessionHasNoErrors();

        // Ein zweites Storno derselben Fahrt ist ausgeschlossen.
        $this->postTrip($this->payload($vehicle, ['corrects_travel_log_id' => $original->sqid, 'correction_reason' => 'nochmal', 'odometer_start_km' => 1098, 'odometer_end_km' => 1148]))
            ->assertSessionHasErrors('corrects_travel_log_id');
    }

    public function test_report_sums_per_trip_kind_and_private_share(): void {
        $vehicle = $this->vehicle();
        $this->trip($vehicle, ['date' => '2030-06-10', 'trip_kind' => TripKind::Business->value, 'distance_km' => 100, 'odometer_start_km' => 1000, 'odometer_end_km' => 1100]);
        $this->trip($vehicle, ['date' => '2030-06-11', 'trip_kind' => TripKind::Private_->value, 'distance_km' => 50, 'odometer_start_km' => 1100, 'odometer_end_km' => 1150]);
        $this->trip($vehicle, ['date' => '2030-06-12', 'trip_kind' => TripKind::Commute->value, 'distance_km' => 50, 'odometer_start_km' => 1150, 'odometer_end_km' => 1200]);
        // Storniertes Original zählt nicht mit.
        $original = $this->trip($vehicle, ['date' => '2030-06-13', 'trip_kind' => TripKind::Business->value, 'distance_km' => 30, 'odometer_start_km' => 1200, 'odometer_end_km' => 1230]);
        app(TravelLogService::class)->correct($original, [
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'vehicle_id' => $vehicle->id,
            'vehicle' => TravelLogVehicle::Company->value,
            'trip_kind' => TripKind::Business->value,
            'date' => '2030-06-13',
            'distance_km' => 20,
            'odometer_start_km' => 1200,
            'odometer_end_km' => 1220,
        ], 'Umweg gestrichen', $this->user);

        $session = $this->dateRangeSession('2030-06-01', '2030-06-30');
        $response = $this->actingAs($this->user)->withSession($session)->get(route('reports.logbook', ['vehicle' => $vehicle->sqid]));
        $response->assertOk();
        $totals = $response->viewData('totals');
        $this->assertSame(220, $totals['km']);
        $this->assertSame(120, $totals['by_kind']['business']);
        $this->assertSame(50, $totals['by_kind']['private']);
        $this->assertSame(50, $totals['by_kind']['commute']);
        $this->assertEqualsWithDelta(22.7, $totals['private_share'], 0.01);
        $this->assertSame(4, $totals['trips']);

        $csv = $this->actingAs($this->user)->withSession($session)->get(route('reports.logbook', ['vehicle' => $vehicle->sqid, 'export' => 'csv']));
        $csv->assertOk();
        $content = (string) $csv->getContent();
        $this->assertStringContainsString('#report:logbook', $content);
        $this->assertStringContainsString('Privater Anteil %;;;22.7', $content);
        $this->assertStringContainsString('Umweg gestrichen', $content);

        $pdf = $this->actingAs($this->user)->withSession($session)->get(route('reports.logbook', ['vehicle' => $vehicle->sqid, 'export' => 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Ohne Fahrzeug: Auswahl statt Tabelle.
        $this->actingAs($this->user)->withSession($session)->get(route('reports.logbook'))->assertOk()->assertSee(__('Bitte ein Fahrzeug wählen.'));
    }

    public function test_reimbursement_mode_behaves_as_before(): void {
        $vehicle = $this->vehicle(logbook: false);

        // Ohne km-Stände, ohne Fahrtart-Angabe: erfasst, editierbar, löschbar, Erstattung wie bisher.
        $this->postTrip($this->payload($vehicle, ['trip_kind' => null, 'rate_per_km' => '0.30']))->assertSessionHasNoErrors();
        $log = TravelLog::query()->firstOrFail();
        $this->assertNull($log->odometer_start_km);
        $this->assertSame(TripKind::Business, $log->trip_kind);
        $this->assertNull($log->locked_at);
        $this->assertEqualsWithDelta(15.0, (float) $log->reimbursement_total, 0.01);

        $this->actingAs($this->user)->put(route('travel-logs.update', $log), $this->payload($vehicle, ['purpose' => 'geändert', 'rate_per_km' => '0.30']))
            ->assertRedirect(route('travel-logs.index'))->assertSessionHasNoErrors();
        $this->assertSame('geändert', $log->fresh()?->purpose);

        // Vortags-Fahrt bleibt im Erstattungsmodus änderbar (keine Tagesende-Sperre).
        $old = $this->trip($vehicle, ['date' => '2030-06-01', 'odometer_start_km' => null, 'odometer_end_km' => null]);
        app(TravelLogService::class)->update($old, ['purpose' => 'nachträglich']);
        $this->assertSame('nachträglich', $old->fresh()?->purpose);

        // Festschreiben ist im Erstattungsmodus nicht vorgesehen.
        $this->actingAs($this->user)->post(route('travel-logs.lock', $log))->assertRedirect(route('travel-logs.index'))->assertSessionHas('error');
        $this->assertNull($log->refresh()->locked_at);

        $this->actingAs($this->user)->delete(route('travel-logs.destroy', $log))->assertRedirect(route('travel-logs.index'));
        $this->assertDatabaseMissing('travel_logs', ['id' => $log->id]);

        // Fahrt ganz ohne Fuhrpark-Fahrzeug: unverändert wie vor MVP-702.
        $this->postTrip(['date' => CarbonImmutable::today()->toDateString(), 'distance_km' => 10, 'vehicle' => TravelLogVehicle::Private_->value, 'purpose' => 'Botengang'])
            ->assertSessionHasNoErrors();
    }

    public function test_service_rejects_logbook_violations_programmatically(): void {
        $vehicle = $this->vehicle();
        try {
            $this->trip($vehicle, ['odometer_start_km' => null, 'odometer_end_km' => null]);
            $this->fail('Pflichtfelder wurden nicht erzwungen.');
        } catch (LogbookViolationException $e) {
            $this->assertArrayHasKey('odometer_start_km', $e->errors);
        }
    }
}
