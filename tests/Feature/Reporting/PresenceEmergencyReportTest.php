<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceEmergencyReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\Diary\Status as DiaryStatus;
use App\Enums\User\Permission;
use App\Enums\Vacation\VacationStatus;
use App\Models\{Attendance, AttendanceTerminal, AuditLog, Customer, DiaryEntry, Organization, SickLeave, Site, User, Vacation};
use App\Services\Attendance\EmergencyAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Notfall-Anwesenheitsliste (Feature 103, MVP-518): Gruppierung, Stichtags-
 * Rekonstruktion, Standortfilter (nie verstecken), Berechtigung, Audit,
 * Mandantentrennung und Exporte.
 */
class PresenceEmergencyReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $viewer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->viewer = $this->orgUser();
        $this->viewer->givePermissionTo(Permission::ReportPresenceEmergency->value);
    }

    private function openAttendance(User $user, string $startedAt, ?string $device = null, string $source = 'clock'): Attendance {
        return Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'date' => substr($startedAt, 0, 10),
            'started_at' => $startedAt,
            'ended_at' => null,
            'status' => AttendanceStatus::Open->value,
            'source' => $source,
            'started_device' => $device,
        ]);
    }

    public function test_requires_dedicated_permission(): void {
        $plain = $this->orgUser();

        $this->actingAs($plain)->get(route('reports.presence-emergency'))->assertForbidden();
        $this->actingAs($this->viewer)->get(route('reports.presence-emergency'))->assertOk();
    }

    public function test_groups_users_by_available_signals(): void {
        $present = $this->orgUser(['name' => 'Paula Präsent']);
        $offSite = $this->orgUser(['name' => 'Otto Außendienst']);
        $onVacation = $this->orgUser(['name' => 'Ulla Urlaub']);
        $sick = $this->orgUser(['name' => 'Kurt Krank']);
        $unknown = $this->orgUser(['name' => 'Nina Nirgends']);

        $now = CarbonImmutable::now();
        $this->openAttendance($present, $now->subHours(2)->format('Y-m-d H:i:s'));
        $this->openAttendance($offSite, $now->subHours(3)->format('Y-m-d H:i:s'));

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kunde Müller']);
        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $offSite->id,
            'status' => DiaryStatus::InProgress->value,
        ]);

        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $onVacation->id,
            'start_date' => $now->subDay()->toDateString(),
            'end_date' => $now->addDay()->toDateString(),
            'status' => VacationStatus::Approved->value,
        ]);
        SickLeave::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $sick->id,
            'start_date' => $now->toDateString(),
            'end_date' => $now->toDateString(),
        ]);

        $this->actingAs($this->viewer);
        $snapshot = app(EmergencyAttendanceService::class)->snapshot((int) $this->organization->id);

        $names = fn (array $rows): array => array_map(fn (array $r): string => $r['user']->name, $rows);

        $this->assertContains('Paula Präsent', $names($snapshot['present']));
        $this->assertContains('Otto Außendienst', $names($snapshot['off_site']));
        $this->assertSame('Kunde Müller', $snapshot['off_site'][0]['context']);
        $this->assertContains('Ulla Urlaub', $names($snapshot['absent']));
        $this->assertContains('Kurt Krank', $names($snapshot['absent']));
        $this->assertContains('Nina Nirgends', $names($snapshot['unaccounted']));
        $this->assertNotContains('Paula Präsent', $names($snapshot['unaccounted']));
    }

    public function test_reconstructs_presence_for_a_past_point_in_time(): void {
        $user = $this->orgUser(['name' => 'Harry Historisch']);
        Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'date' => '2026-08-03',
            'started_at' => '2026-08-03 08:00:00',
            'ended_at' => '2026-08-03 12:00:00',
            'status' => AttendanceStatus::Closed->value,
            'source' => AttendanceSource::Manual->value,
        ]);

        $this->actingAs($this->viewer);
        $service = app(EmergencyAttendanceService::class);

        $during = $service->snapshot((int) $this->organization->id, CarbonImmutable::parse('2026-08-03 10:00:00'));
        $after = $service->snapshot((int) $this->organization->id, CarbonImmutable::parse('2026-08-03 13:00:00'));

        $names = fn (array $rows): array => array_map(fn (array $r): string => $r['user']->name, $rows);
        $this->assertContains('Harry Historisch', $names($during['present']));
        $this->assertNotContains('Harry Historisch', $names($after['present']));
        $this->assertFalse($during['is_live']);
    }

    public function test_site_filter_maps_terminals_and_never_hides_unmapped(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $siteA = Site::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Werk A']);
        $siteB = Site::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Werk B']);
        [$terminalA] = AttendanceTerminal::issue((int) $this->organization->id, 'Terminal A', (int) $siteA->id);
        AttendanceTerminal::issue((int) $this->organization->id, 'Terminal B', (int) $siteB->id);

        $atA = $this->orgUser(['name' => 'Anna A']);
        $atB = $this->orgUser(['name' => 'Bernd B']);
        $browser = $this->orgUser(['name' => 'Britta Browser']);

        $now = CarbonImmutable::now()->subHour()->format('Y-m-d H:i:s');
        $this->openAttendance($atA, $now, 'Terminal A', AttendanceSource::Terminal->value);
        $this->openAttendance($atB, $now, 'Terminal B', AttendanceSource::Terminal->value);
        $this->openAttendance($browser, $now);

        $this->actingAs($this->viewer);
        $snapshot = app(EmergencyAttendanceService::class)->snapshot((int) $this->organization->id, null, (int) $siteA->id);

        $names = fn (array $rows): array => array_map(fn (array $r): string => $r['user']->name, $rows);
        $this->assertSame(['Anna A'], $names($snapshot['present']));
        $this->assertSame('Werk A', $snapshot['present'][0]['site_name']);
        $this->assertSame(['Britta Browser'], $names($snapshot['present_unmapped']));
        $this->assertNotContains('Bernd B', $names($snapshot['present']));
    }

    public function test_other_tenants_are_invisible(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignUser = User::factory()->user()->create(['organization_id' => $foreignOrg->id, 'name' => 'Frieda Fremd']);
        Attendance::factory()->create([
            'organization_id' => $foreignOrg->id,
            'user_id' => $foreignUser->id,
            'date' => CarbonImmutable::now()->toDateString(),
            'started_at' => CarbonImmutable::now()->subHour(),
            'ended_at' => null,
            'status' => AttendanceStatus::Open->value,
            'source' => AttendanceSource::Clock->value,
        ]);

        $this->actingAs($this->viewer);
        $snapshot = app(EmergencyAttendanceService::class)->snapshot((int) $this->organization->id);

        $all = array_merge($snapshot['present'], $snapshot['present_unmapped'], $snapshot['off_site'], $snapshot['absent'], $snapshot['unaccounted']);
        $names = array_map(fn (array $r): string => $r['user']->name, $all);
        $this->assertNotContains('Frieda Fremd', $names);
    }

    public function test_every_screen_access_is_audited(): void {
        $this->actingAs($this->viewer)->get(route('reports.presence-emergency'))->assertOk();

        $this->assertTrue(
            AuditLog::query()
                ->where('organization_id', $this->organization->id)
                ->where('user_id', $this->viewer->id)
                ->where('event', 'report.presenceEmergencyViewed')
                ->exists(),
        );
    }

    public function test_csv_export_returns_csv_and_audits(): void {
        $response = $this->actingAs($this->viewer)->get(route('reports.presence-emergency', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertTrue(AuditLog::query()->where('event', 'report.exported')->exists());
    }

    public function test_pdf_export_returns_pdf(): void {
        $response = $this->actingAs($this->viewer)->get(route('reports.presence-emergency', ['export' => 'pdf']));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
