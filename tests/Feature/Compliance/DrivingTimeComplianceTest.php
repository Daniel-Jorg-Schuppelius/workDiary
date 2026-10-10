<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DrivingTimeComplianceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Compliance;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Travel\TravelLogVehicle;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Fleet\Vehicle;
use App\Models\Platform\{Team, User};
use App\Models\Travel\TravelLog;
use App\Notifications\GenericEventNotification;
use App\Services\Compliance\{DrivingTimeBudget, DrivingTimeComplianceChecker};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Feature 144 (MVP-719): Lenk-/Ruhezeit-Scan, Geltungsschalter (Org-Setting +
 * Fahrzeug-Flag), Nachweis-Export, Benachrichtigung und Dispositions-Budget.
 */
class DrivingTimeComplianceTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private User $driver;

    private Vehicle $truck;

    protected function setUp(): void {
        parent::setUp();
        // Fixe „Jetzt" (Mittwoch) — Scan-Fenster und Wochenbezug bleiben stabil.
        $this->travelTo(Carbon::parse('2026-06-10 12:00:00'));
        config()->set('app.display_timezone', 'UTC');
        $this->setUpOrganization([
            'timezone' => 'UTC',
            'settings' => ['compliance' => ['driving_time_rules' => true]],
        ]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Fahrer Frey']);
        $this->truck = Vehicle::factory()->subjectToDrivingTimeRules()->create(['organization_id' => $this->organization->id, 'label' => 'Sattelzug']);
    }

    private function trip(string $start, string $end, ?Vehicle $vehicle = null, ?User $driver = null): TravelLog {
        return TravelLog::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => ($driver ?? $this->driver)->id,
            'vehicle_id' => ($vehicle ?? $this->truck)->id,
            'vehicle' => TravelLogVehicle::Company->value,
            'date' => substr($start, 0, 10),
            'started_at' => $start,
            'ended_at' => $end,
            'distance_km' => 120,
            'reimbursable' => false,
        ]);
    }

    private function scan(): void {
        $this->artisan('compliance:scan-findings', ['--days' => 30])->assertExitCode(0);
    }

    public function test_scan_persists_driving_time_findings_in_their_own_category(): void {
        // Montag 06:00–11:00 = 5 h ohne Fahrtunterbrechung (Art. 7).
        $this->trip('2026-06-08 06:00:00', '2026-06-08 11:00:00');

        $this->scan();

        $finding = ComplianceFinding::query()
            ->where('organization_id', $this->organization->id)
            ->where('category', DrivingTimeComplianceChecker::CATEGORY)
            ->first();
        $this->assertNotNull($finding);
        $this->assertSame(DrivingTimeComplianceChecker::KIND_BREAK_MISSING, $finding->rule_code);
        $this->assertSame($this->driver->id, (int) $finding->subject_id);
        $this->assertSame('2026-06-08', $finding->scope_date->toDateString());
        $this->assertSame(300, $finding->detected_value);
        $this->assertSame(270, $finding->threshold_value);
    }

    /** UI-Fuzz 2026-09-21: Fahrten sind Ortszeit — die Auswertung rechnete sie wie UTC um und verschob sie in den Folgetag. */
    public function test_trip_times_are_read_as_local_wall_time(): void {
        config()->set('app.display_timezone', 'Europe/Berlin');
        $this->organization->forceFill(['timezone' => 'Europe/Berlin'])->save();
        // Montag 23:00 bis Dienstag 04:00 Ortszeit = 5 h ohne Fahrtunterbrechung.
        $this->trip('2026-06-08 23:00:00', '2026-06-09 04:00:00');

        $this->scan();

        $finding = ComplianceFinding::query()->where('category', DrivingTimeComplianceChecker::CATEGORY)->firstOrFail();
        $this->assertSame(DrivingTimeComplianceChecker::KIND_BREAK_MISSING, $finding->rule_code);
        $this->assertSame('2026-06-08', $finding->scope_date->toDateString());
    }

    public function test_vehicle_without_flag_is_ignored(): void {
        $van = Vehicle::factory()->create(['organization_id' => $this->organization->id]);
        $this->trip('2026-06-08 06:00:00', '2026-06-08 11:00:00', $van);

        $this->scan();

        $this->assertSame(0, ComplianceFinding::query()->where('category', DrivingTimeComplianceChecker::CATEGORY)->count());
    }

    public function test_organization_without_setting_produces_nothing(): void {
        $this->organization->forceFill(['settings' => ['compliance' => ['driving_time_rules' => false]]])->save();
        $this->trip('2026-06-08 06:00:00', '2026-06-08 11:00:00');

        $this->scan();

        $this->assertSame(0, ComplianceFinding::query()->where('category', DrivingTimeComplianceChecker::CATEGORY)->count());
        $this->assertNull(app(DrivingTimeBudget::class)->remainingFor($this->driver->fresh(), CarbonImmutable::parse('2026-06-08')));
    }

    public function test_new_finding_notifies_driver(): void {
        Notification::fake();
        $this->trip('2026-06-08 06:00:00', '2026-06-08 11:00:00');

        $this->scan();

        Notification::assertSentTo(
            $this->driver,
            GenericEventNotification::class,
            fn (GenericEventNotification $n): bool => $n->event === NotificationEvent::DrivingTimeViolation,
        );
    }

    public function test_evidence_export_returns_csv_and_pdf(): void {
        $this->trip('2026-06-08 06:00:00', '2026-06-08 11:00:00');
        $session = $this->dateRangeSession('2026-06-01', '2026-06-14');

        $csv = $this->actingAs($this->admin)->withSession($session)->get(route('reports.driving-time-evidence'));
        $csv->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Fahrer Frey', $csv->getContent());
        $this->assertStringContainsString('Sattelzug', $csv->getContent());
        $this->assertStringContainsString('5:00 h', $csv->getContent());

        $pdf = $this->actingAs($this->admin)->withSession($session)->get(route('reports.driving-time-evidence', ['format' => 'pdf']));
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_evidence_export_respects_the_team_filter(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Fahrer Ohneteam']);
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $team->members()->attach($this->driver->id);
        $this->trip('2026-06-08 06:00:00', '2026-06-08 11:00:00');
        $this->trip('2026-06-09 06:00:00', '2026-06-09 11:00:00', driver: $other);

        $csv = (string) $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2026-06-01', '2026-06-14'))
            ->get(route('reports.driving-time-evidence', ['team' => Sqid::encode(Team::class, (int) $team->id)]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Fahrer Frey', $csv);
        $this->assertStringNotContainsString('Fahrer Ohneteam', $csv);
    }

    public function test_report_offers_the_evidence_as_pdf(): void {
        $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2026-06-01', '2026-06-14'))
            ->get(route('reports.arbzg-compliance'))
            ->assertOk()
            ->assertSee(__('compliance.driving.button_pdf'))
            ->assertSee('format=pdf', false);
    }

    public function test_evidence_export_is_hidden_without_setting_and_for_plain_users(): void {
        $session = $this->dateRangeSession('2026-06-01', '2026-06-14');
        $this->actingAs($this->driver)->withSession($session)->get(route('reports.driving-time-evidence'))->assertForbidden();

        $this->organization->forceFill(['settings' => ['compliance' => ['driving_time_rules' => false]]])->save();
        app()->instance('currentOrganization', $this->organization->fresh());
        $this->actingAs($this->admin)->withSession($session)->get(route('reports.driving-time-evidence'))->assertNotFound();
    }

    public function test_report_shows_driving_time_findings_with_category_filter(): void {
        $this->trip('2026-06-08 06:00:00', '2026-06-08 11:00:00');

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2026-06-01', '2026-06-14'))
            ->get(route('reports.arbzg-compliance', ['category' => DrivingTimeComplianceChecker::CATEGORY]));

        $response->assertOk();
        $response->assertSee(__('compliance.report.kind.' . DrivingTimeComplianceChecker::KIND_BREAK_MISSING));
        $response->assertSee(__('compliance.driving.button'));
    }

    public function test_budget_reflects_todays_driving(): void {
        // Heute (Mi): 2 h, 10 min Lücke, 2 h → 4 h seit letzter gültiger Unterbrechung.
        $this->trip('2026-06-10 06:00:00', '2026-06-10 08:00:00');
        $this->trip('2026-06-10 08:10:00', '2026-06-10 10:10:00');
        // Montag 9 h 30 → eine der zwei 10-h-Verlängerungen ist verbraucht.
        $this->trip('2026-06-08 05:00:00', '2026-06-08 09:30:00');
        $this->trip('2026-06-08 10:15:00', '2026-06-08 15:15:00');

        $budget = app(DrivingTimeBudget::class)->remainingFor($this->driver, CarbonImmutable::parse('2026-06-10'));

        $this->assertNotNull($budget);
        $this->assertSame(600, $budget['daily_limit']);
        $this->assertSame(240, $budget['daily_driven']);
        $this->assertSame(360, $budget['daily_remaining']);
        $this->assertSame(30, $budget['until_break']);
        $this->assertSame(810, $budget['weekly_driven']);
        $this->assertSame(3360 - 810, $budget['weekly_remaining']);
        $this->assertSame(5400 - 810, $budget['fortnight_remaining']);
    }

    public function test_vehicle_flag_can_be_saved_by_admin(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->put(route('vehicles.update', $vehicle), [
            'license_plate' => $vehicle->license_plate,
            'vehicle_type' => $vehicle->vehicle_type->value,
            'propulsion' => $vehicle->propulsion->value,
            'subject_to_driving_time_rules' => '1',
        ])->assertRedirect();

        $this->assertTrue($vehicle->fresh()->subject_to_driving_time_rules);
    }

    /** MVP-1014: Der zweite Fahrer lenkt nicht, seine Zeit im Fahrzeug ist aber keine Ruhe. */
    public function test_co_driver_trips_count_for_the_co_driver_without_driving_time(): void {
        $partner = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Beifahrer Bauer']);
        // Montag: Frey fährt 06:00–10:30 und 11:15–15:30 mit Bauer als zweitem Fahrer, Bauer fährt 16:00–20:00 mit Frey.
        $this->trip('2026-06-08 06:00:00', '2026-06-08 10:30:00')->update(['co_driver_user_id' => $partner->id]);
        $this->trip('2026-06-08 11:15:00', '2026-06-08 15:30:00')->update(['co_driver_user_id' => $partner->id]);
        $this->trip('2026-06-08 16:00:00', '2026-06-08 20:00:00', driver: $partner)->update(['co_driver_user_id' => $this->driver->id]);
        // Dienstag 03:00: Bauer fährt wieder — nur 7 h nach dem Ende der gemeinsamen Fahrt.
        $this->trip('2026-06-09 03:00:00', '2026-06-09 05:00:00', driver: $partner);

        $findings = app(\App\Services\Compliance\ComplianceScanService::class)->drivingTimeFindingsForRange($this->organization, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-10'));

        $this->assertSame([], $findings[$this->driver->id] ?? []);
        $partnerFindings = $findings[$partner->id] ?? [];
        $this->assertSame([DrivingTimeComplianceChecker::KIND_DAILY_REST], array_values(array_unique(array_map(static fn ($f): string => $f->kind, $partnerFindings))));
        $this->assertSame(420, $partnerFindings[0]->value);
    }

    public function test_the_form_stores_co_driver_and_ferry_flag(): void {
        $partner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->driver)->get(route('travel-logs.create'))->assertOk()->assertSee(__('travel.driving_time.co_driver'));
        $payload = ['date' => '2026-06-09', 'start_time' => '08:00', 'end_time' => '09:00', 'vehicle' => TravelLogVehicle::Company->value, 'vehicle_id' => $this->truck->sqid,
            'distance_km' => '10', 'odometer_start_km' => '1000', 'odometer_end_km' => '1010', 'is_ferry_or_train' => '1'];
        $this->actingAs($this->driver)->post(route('travel-logs.store'), $payload + ['co_driver_user_id' => $this->driver->sqid])
            ->assertSessionHasErrors('co_driver_user_id');
        $this->actingAs($this->driver)->post(route('travel-logs.store'), $payload + ['co_driver_user_id' => $partner->sqid])->assertSessionHasNoErrors();

        $log = TravelLog::query()->where('user_id', $this->driver->id)->latest('id')->firstOrFail();
        $this->assertSame($partner->id, $log->co_driver_user_id);
        $this->assertTrue($log->is_ferry_or_train);
    }
}
