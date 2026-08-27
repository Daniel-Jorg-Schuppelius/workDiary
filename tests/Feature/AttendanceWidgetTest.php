<?php
/*
 * Created on   : Mon May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceWidgetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{User, UserDashboardWidget};
use App\Services\Attendance\AttendanceClockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AttendanceWidgetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_dashboard_does_not_render_attendance_chip_when_idle(): void {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk();

        $html = $response->getContent() ?: '';
        $this->assertStringNotContainsString(route('attendance.clock-out'), $html);
    }

    public function test_dashboard_shows_attendance_clock_tile(): void {
        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent() ?: '';

        // Die Stempeluhr-Kachel rendert attendances._panel (Marker
        // data-attendance-panel) — der Header-Chip tut das nicht.
        $this->assertStringContainsString('data-attendance-panel', $html);
    }

    public function test_attendance_clock_tile_can_be_hidden(): void {
        UserDashboardWidget::create([
            'user_id' => $this->user->id,
            'widget_key' => 'attendance-clock',
            'sort_order' => 0,
            'hidden' => true,
        ]);

        $html = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringNotContainsString('data-attendance-panel', $html);
    }

    public function test_dashboard_shows_clock_out_form_when_punched_in(): void {
        $attendance = app(AttendanceClockService::class)->clockIn($this->user);
        $attendance->forceFill(['started_at' => now()->subHour()])->saveQuietly();
        $attendance->refresh();
        $this->assertNotNull($attendance->started_at);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Ausstempeln'))
            ->assertSee(route('attendance.clock-out'), false);

        $html = $response->getContent() ?: '';
        $this->assertStringContainsString($attendance->started_at->toIso8601String(), $html);
    }
}
