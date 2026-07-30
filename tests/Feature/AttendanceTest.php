<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\{Attendance, Project, TimeEntry, User};
use App\Services\Attendance\AttendanceClockService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class AttendanceTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_clock_in_creates_open_attendance(): void {
        $svc = app(AttendanceClockService::class);
        $a = $svc->clockIn($this->user);

        $this->assertNull($a->ended_at);
        $this->assertSame(AttendanceStatus::Open, $a->status);
        $this->assertSame($a->id, $svc->current($this->user)?->id);
    }

    public function test_double_clock_in_is_rejected(): void {
        $svc = app(AttendanceClockService::class);
        $svc->clockIn($this->user);

        $this->expectException(RuntimeException::class);
        $svc->clockIn($this->user);
    }

    public function test_clock_out_closes_attendance_and_computes_duration(): void {
        $svc = app(AttendanceClockService::class);
        $a = $svc->clockIn($this->user);
        $a->forceFill(['started_at' => now()->subMinutes(120)])->saveQuietly();

        $closed = $svc->clockOut($this->user, ['break_minutes' => 30]);
        $this->assertNotNull($closed);
        $this->assertNotNull($closed->ended_at);
        $this->assertSame(AttendanceStatus::Closed, $closed->status);
        $this->assertGreaterThanOrEqual(85, $closed->duration_minutes);
        $this->assertLessThanOrEqual(95, $closed->duration_minutes);
    }

    public function test_clock_out_without_open_returns_null(): void {
        $svc = app(AttendanceClockService::class);
        $this->assertNull($svc->clockOut($this->user));
    }

    public function test_cancel_marks_attendance_cancelled(): void {
        $svc = app(AttendanceClockService::class);
        $svc->clockIn($this->user);
        $cancelled = $svc->cancel($this->user, 'wrong button');
        $this->assertNotNull($cancelled);
        $this->assertSame(AttendanceStatus::Cancelled, $cancelled->status);
        $this->assertNull($svc->current($this->user));
    }

    public function test_auto_close_handles_stale_open_sessions(): void {
        $svc = new AttendanceClockService(maxOpenMinutes: 60);
        $a = $svc->clockIn($this->user);
        $a->forceFill(['started_at' => now()->subHours(20)])->saveQuietly();

        $count = $svc->autoCloseStaleSessions(Carbon::now());

        $this->assertSame(1, $count);
        $a->refresh();
        $this->assertSame(AttendanceStatus::AutoClosed, $a->status);
        $this->assertNotNull($a->ended_at);
    }

    public function test_partial_unique_index_blocks_two_open_attendances(): void {
        Attendance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => now()->subHour(),
            'date' => now()->startOfDay(),
        ]);

        $this->expectException(QueryException::class);
        Attendance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => now(),
            'date' => now()->startOfDay(),
        ]);
    }

    public function test_web_clock_in_clock_out_endpoints(): void {
        $this->actingAs($this->user);

        $this->post(route('attendance.clock-in'), [])
            ->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'ended_at' => null,
        ]);

        $this->post(route('attendance.clock-out'), ['break_minutes' => 0])
            ->assertRedirect();
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $this->user->id,
            'ended_at' => null,
        ]);
    }

    public function test_api_clock_in_returns_attendance_payload(): void {
        // Token mit vollen Abilities: die API-Routen sind seit MVP-133/Rang 60
        // scope-geschützt (ability:attendance:write); actingAs ohne AccessToken
        // führt sonst zu 401 (kein currentAccessToken).
        Sanctum::actingAs($this->user, ['*']);

        $res = $this->postJson(route('api.attendance.clock-in'), []);
        $res->assertCreated()
            ->assertJsonStructure(['id', 'user_id', 'started_at', 'status']);

        $this->postJson(route('api.attendance.clock-in'), [])->assertStatus(409);
        $this->postJson(route('api.attendance.clock-out'), [])->assertOk();
        $this->postJson(route('api.attendance.clock-out'), [])->assertStatus(404);
    }

    public function test_time_entry_with_activity_admin_does_not_require_project(): void {
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'minutes' => 30,
            'kind' => TimeEntryKind::Work->value,
            'activity_type' => TimeEntryActivityType::Admin->value,
            'description' => 'Verwaltung',
        ]);

        $this->assertNull($entry->project_id);
        $this->assertSame(TimeEntryActivityType::Admin, $entry->activity_type);
    }

    public function test_time_entry_with_project_activity_requires_project_id(): void {
        $this->expectException(\InvalidArgumentException::class);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'minutes' => 30,
            'activity_type' => TimeEntryActivityType::Project->value,
        ]);
    }

    public function test_attendance_index_view_renders(): void {
        $this->actingAs($this->user);

        Attendance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHour(),
            'date' => now()->startOfDay(),
            'status' => AttendanceStatus::Closed->value,
        ]);

        $this->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Stempelungen')
            ->assertSee(__('Abgeschlossen'));
    }

    public function test_attendance_index_follows_global_header_range(): void {
        $this->actingAs($this->user);

        $june = Attendance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => CarbonImmutable::parse('2026-06-10 08:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-06-10 16:00:00'),
            'date' => '2026-06-10',
            'status' => AttendanceStatus::Closed->value,
        ]);
        $july = Attendance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => CarbonImmutable::parse('2026-07-15 08:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-15 16:00:00'),
            'date' => '2026-07-15',
            'status' => AttendanceStatus::Closed->value,
        ]);

        // Header-Zeitraum Juni → nur die Juni-Stempelung erscheint.
        $ids = $this->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('attendance.index'))
            ->assertOk()
            ->viewData('attendances')
            ->pluck('id');
        $this->assertTrue($ids->contains($june->id));
        $this->assertFalse($ids->contains($july->id));

        // Explizite from/to-Query (Bookmark) überstimmt den Header-Zeitraum.
        $ids = $this->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('attendance.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->viewData('attendances')
            ->pluck('id');
        $this->assertTrue($ids->contains($july->id));
        $this->assertFalse($ids->contains($june->id));
    }

    public function test_today_follows_header_range_only_for_single_day(): void {
        $this->actingAs($this->user);

        // Einzeltag im Header → „Heute" zeigt genau diesen Tag.
        $this->withSession($this->dateRangeSession('2026-07-15', '2026-07-15'))
            ->get(route('today.show'))
            ->assertOk()
            ->assertViewHas('day', fn ($day) => $day->toDateString() === '2026-07-15');

        // Mehrtages-Zeitraum → weiterhin heute.
        $this->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('today.show'))
            ->assertOk()
            ->assertViewHas('day', fn ($day) => $day->isToday());

        // Explizites ?date= überstimmt den Einzeltag-Header.
        $this->withSession($this->dateRangeSession('2026-07-15', '2026-07-15'))
            ->get(route('today.show', ['date' => '2026-07-20']))
            ->assertOk()
            ->assertViewHas('day', fn ($day) => $day->toDateString() === '2026-07-20');
    }

    public function test_today_dashboard_renders_with_soll_ist_unverteilt(): void {
        $this->actingAs($this->user);

        Attendance::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'started_at' => now()->startOfDay()->addHours(8),
            'ended_at' => now()->startOfDay()->addHours(12),
            'date' => now()->startOfDay(),
        ]);

        $this->get(route('today.show'))
            ->assertOk()
            ->assertSee(__('Heute'))
            ->assertSee(__('Soll'))
            ->assertSee(__('Anwesenheit'))
            ->assertSee(__('Unverteilt'))
            // Eingabeleiste (Toggl-artig) auf der Heute-Seite.
            ->assertSee(__('Woran arbeitest du?'))
            // Zusammenlegung mit dem Tagesabschluss (MVP-015): „Heute" zeigt
            // jetzt auch Warnungen, Bilanz (inkl. Pausen) und die Abschluss-Aktion.
            ->assertSee(__('day-close.section.issues'))
            ->assertSee(__('day-close.section.balance'))
            ->assertSee(__('day-close.field.required_break'))
            ->assertSee(__('day-close.action.close_day'));
    }

    public function test_backfill_command_creates_attendance_from_entries(): void {
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'P',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        $today = CarbonImmutable::today();
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $project->id,
            'date' => $today,
            'started_at' => $today->setTime(8, 0),
            'ended_at' => $today->setTime(12, 0),
            'minutes' => 240,
            'kind' => TimeEntryKind::Work->value,
            'activity_type' => TimeEntryActivityType::Project->value,
        ]);

        $this->artisan('attendance:backfill')->assertExitCode(0);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->user->id,
            'date' => $today->toDateString() . ' 00:00:00',
            'source' => AttendanceSource::Import->value,
        ]);
    }
}
