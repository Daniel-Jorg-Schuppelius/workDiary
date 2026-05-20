<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkBalanceReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryActivityType;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Reporting\WorkBalanceCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class WorkBalanceReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_daily_balance_aggregates_attendance_breaks_and_entries(): void {
        $day = CarbonImmutable::create(2026, 5, 4); // Mon
        $start = $day->setTime(8, 0);
        $end = $day->setTime(17, 0); // 9h gross, 30m break => 8h30 net

        Attendance::factory()->create([
            'user_id' => $this->user->id,
            'date' => $day,
            'started_at' => $start,
            'ended_at' => $end,
            'break_minutes_manual' => 30,
        ]);

        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test',
            'status' => ProjectStatus::Active->value,
            'billable' => true,
        ]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'date' => $day->toDateString(),
            'minutes' => 300, // 5h project
        ]);
        TimeEntry::factory()->administration()->create([
            'user_id' => $this->user->id,
            'date' => $day->toDateString(),
            'minutes' => 60, // 1h admin
        ]);
        TimeEntry::factory()->travel()->create([
            'user_id' => $this->user->id,
            'date' => $day->toDateString(),
            'minutes' => 45, // 45m travel (kind=travel → counts via config('timesheet.flex.count_kinds'))
        ]);

        $calc = app(WorkBalanceCalculator::class);
        $balance = $calc->daily($this->user, $day);

        $this->assertSame(510, $balance->attendanceMinutes); // 9h - 30m
        $this->assertSame(30, $balance->breakMinutes);
        $this->assertSame(405, $balance->trackedMinutes); // 5h + 1h + 45m travel
        $this->assertSame(105, $balance->untrackedMinutes); // 510 - 405
        $this->assertArrayHasKey(TimeEntryActivityType::Project->value, $balance->byActivity);
        $this->assertArrayHasKey(TimeEntryActivityType::Admin->value, $balance->byActivity);
        $this->assertArrayHasKey(TimeEntryActivityType::Travel->value, $balance->byActivity);
        $this->assertSame(45, $balance->byKind[TimeEntryKind::Travel->value]);
        $this->assertSame(360, $balance->byKind[TimeEntryKind::Work->value]);
    }

    public function test_open_attendance_uses_current_time_for_duration(): void {
        $day = CarbonImmutable::today();
        Attendance::factory()->open()->create([
            'user_id' => $this->user->id,
            'date' => $day,
            'started_at' => CarbonImmutable::now()->subMinutes(120),
            'ended_at' => null,
            'duration_minutes' => 0,
            'break_minutes_manual' => 0,
        ]);

        $calc = app(WorkBalanceCalculator::class);
        $balance = $calc->daily($this->user, $day);

        // ~120 min, allow drift
        $this->assertGreaterThanOrEqual(115, $balance->attendanceMinutes);
        $this->assertLessThanOrEqual(125, $balance->attendanceMinutes);
    }

    public function test_month_aggregates_days(): void {
        $first = CarbonImmutable::create(2026, 5, 1);
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test',
            'status' => ProjectStatus::Active->value,
            'billable' => true,
        ]);

        foreach ([4, 5, 6] as $d) {
            TimeEntry::factory()->create([
                'user_id' => $this->user->id,
                'project_id' => $project->id,
                'date' => $first->setDate(2026, 5, $d)->toDateString(),
                'minutes' => 240,
            ]);
        }

        $calc = app(WorkBalanceCalculator::class);
        $period = $calc->month($this->user, 2026, 5);

        $this->assertSame(720, $period->trackedMinutes);
        $this->assertSame('2026-05-01', $period->from);
        $this->assertSame('2026-05-31', $period->to);
        $this->assertCount(31, $period->days);
    }

    public function test_index_renders_and_pdf_export_returns_download(): void {
        $this->actingAs($this->user);

        $this->get(route('reports.work-balance', ['from' => '2026-05-01', 'to' => '2026-05-31']))
            ->assertOk()
            ->assertSee(__('Arbeitsbilanz'));

        $response = $this->get(route('reports.work-balance', ['from' => '2026-05-01', 'to' => '2026-05-31', 'export' => 'pdf']));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('arbeitsbilanz-', (string) $response->headers->get('content-disposition'));
    }

    public function test_index_uses_global_header_date_range_by_default(): void {
        $this->actingAs($this->user);

        // Header selects May 2026
        session()->put('ui.daterange.preset', 'custom');
        session()->put('ui.daterange.from', '2026-05-01');
        session()->put('ui.daterange.to', '2026-05-31');

        $response = $this->get(route('reports.work-balance'));
        $response->assertOk();

        $period = $response->viewData('period');
        $this->assertSame('2026-05-01', $period->from);
        $this->assertSame('2026-05-31', $period->to);
    }

    public function test_tracked_minutes_exclude_break_and_absence_activity_types(): void {
        $day = CarbonImmutable::create(2026, 5, 4);
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Filter-Project',
            'status' => ProjectStatus::Active->value,
            'billable' => true,
        ]);

        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'date' => $day->toDateString(),
            'minutes' => 240, // counts
        ]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => null,
            'activity_type' => TimeEntryActivityType::Break_->value,
            'date' => $day->toDateString(),
            'minutes' => 60, // must NOT count
        ]);
        TimeEntry::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => null,
            'activity_type' => TimeEntryActivityType::Absence->value,
            'date' => $day->toDateString(),
            'minutes' => 480, // must NOT count
        ]);
        TimeEntry::factory()->travel()->create([
            'user_id' => $this->user->id,
            'date' => $day->toDateString(),
            'minutes' => 45, // counts because kind=travel ∈ count_kinds default
        ]);

        $calc = app(WorkBalanceCalculator::class);
        $balance = $calc->daily($this->user, $day);

        $this->assertSame(285, $balance->trackedMinutes); // 240 + 45
    }

    public function test_admin_can_view_other_users_work_balance(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $other = $this->user; // regular user

        $day = CarbonImmutable::create(2026, 5, 4);
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Admin-View',
            'status' => ProjectStatus::Active->value,
            'billable' => true,
        ]);
        TimeEntry::factory()->create([
            'user_id' => $other->id,
            'project_id' => $project->id,
            'date' => $day->toDateString(),
            'minutes' => 200,
        ]);

        $this->actingAs($admin);
        $response = $this->get(route('reports.work-balance', [
            'user' => $other->id,
            'from' => '2026-05-01',
            'to' => '2026-05-31',
        ]));

        $response->assertOk();
        $this->assertSame((int) $other->id, (int) $response->viewData('user')->id);
        $this->assertSame(200, $response->viewData('period')->trackedMinutes);
    }

    public function test_non_admin_cannot_view_other_users_work_balance(): void {
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->user); // regular user
        $response = $this->get(route('reports.work-balance', ['user' => $other->id]));

        $response->assertForbidden();
    }

    public function test_non_admin_passing_own_user_id_is_allowed(): void {
        $this->actingAs($this->user);
        $response = $this->get(route('reports.work-balance', ['user' => $this->user->id]));

        $response->assertOk();
        $this->assertSame((int) $this->user->id, (int) $response->viewData('user')->id);
    }
}
