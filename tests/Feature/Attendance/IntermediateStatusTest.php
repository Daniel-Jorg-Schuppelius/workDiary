<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntermediateStatusTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Attendance;

use App\Models\{AttendanceTerminal, User, UserBadge};
use App\Services\Attendance\{AttendanceClockService, EmergencyAttendanceService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-532 (Feature 103, Q1-Drittabgleich): Zwischen-Status Homeoffice und
 * Dienstgang — Browser-Toggle, Terminal-Ereignistyp, Abschluss beim Gehen
 * und Einordnung in Board/Notfallliste.
 */
class IntermediateStatusTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgUser();
    }

    public function test_browser_toggle_starts_and_ends_homeoffice(): void {
        app(AttendanceClockService::class)->clockIn($this->user);

        $this->actingAs($this->user)
            ->post(route('attendance.intermediate'), ['kind' => 'homeoffice'])
            ->assertRedirect();
        $current = app(AttendanceClockService::class)->current($this->user);
        $this->assertNotNull($current->homeoffice_started_at);

        $this->travel(30)->minutes();
        $this->actingAs($this->user)
            ->post(route('attendance.intermediate'), ['kind' => 'homeoffice'])
            ->assertRedirect();
        $current = app(AttendanceClockService::class)->current($this->user);
        $this->assertNull($current->homeoffice_started_at);
        $this->assertSame(30, $current->homeoffice_minutes);
    }

    public function test_toggle_without_open_attendance_reports_error(): void {
        $this->actingAs($this->user)
            ->post(route('attendance.intermediate'), ['kind' => 'errand'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_clock_out_finalizes_running_errand(): void {
        $clock = app(AttendanceClockService::class);
        $clock->clockIn($this->user);
        $clock->toggleIntermediate($this->user, 'errand');

        $this->travel(45)->minutes();
        $attendance = $clock->clockOut($this->user);

        $this->assertNull($attendance->errand_started_at);
        $this->assertSame(45, $attendance->errand_minutes);
    }

    public function test_terminal_event_type_toggles_errand(): void {
        [, $token] = AttendanceTerminal::issue((int) $this->organization->id, 'Halle West');
        UserBadge::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'badge_hash' => UserBadge::hashBadge('IM-01'),
        ]);

        $this->postJson('/api/terminal/ingest/' . $token, ['badge_uid' => 'IM-01'])
            ->assertOk()->assertJson(['status' => 'clocked_in']);
        $this->postJson('/api/terminal/ingest/' . $token, ['badge_uid' => 'IM-01', 'event_type' => 'errand'])
            ->assertOk()->assertJson(['status' => 'errand_started']);
        $this->postJson('/api/terminal/ingest/' . $token, ['badge_uid' => 'IM-01', 'event_type' => 'errand'])
            ->assertOk()->assertJson(['status' => 'errand_ended']);
    }

    public function test_running_intermediate_groups_as_off_site(): void {
        $clock = app(AttendanceClockService::class);
        $clock->clockIn($this->user);
        $clock->toggleIntermediate($this->user, 'homeoffice');

        $this->actingAs($this->user);
        $snapshot = app(EmergencyAttendanceService::class)->snapshot((int) $this->organization->id);

        $names = array_map(fn (array $r): string => $r['user']->name, $snapshot['off_site']);
        $this->assertContains($this->user->name, $names);
        $this->assertSame((string) __('attendance.intermediate.homeoffice'), $snapshot['off_site'][0]['context']);
        $this->assertNotContains($this->user->name, array_map(fn (array $r): string => $r['user']->name, $snapshot['present']));
    }

    public function test_today_panel_shows_intermediate_buttons(): void {
        app(AttendanceClockService::class)->clockIn($this->user);

        $this->actingAs($this->user)
            ->get(route('today.show'))
            ->assertOk()
            ->assertSee((string) __('attendance.intermediate.start_homeoffice'))
            ->assertSee((string) __('attendance.intermediate.start_errand'));
    }
}
