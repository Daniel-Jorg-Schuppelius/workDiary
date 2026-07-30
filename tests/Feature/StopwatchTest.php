<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StopwatchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{DiaryEntry, Project, TimeEntry, Timesheet, User};
use App\Services\Timesheet\Stopwatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class StopwatchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'SW-Projekt',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_start_creates_running_entry_and_stop_closes_it(): void {
        $sw = app(Stopwatch::class);
        $ts = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'status' => TimesheetStatus::Draft->value,
        ]);

        $entry = $sw->start($this->user, $ts);
        $this->assertNotNull($entry->started_at);
        $this->assertNull($entry->ended_at);
        $this->assertSame($entry->id, $sw->current($this->user)?->id);

        // simulate elapsed time
        $entry->forceFill(['started_at' => now()->subMinutes(45)])->saveQuietly();

        $stopped = $sw->stop($this->user);
        $this->assertNotNull($stopped);
        $this->assertNotNull($stopped->ended_at);
        $this->assertGreaterThanOrEqual(40, (int) $stopped->minutes);
    }

    public function test_cannot_start_when_timesheet_signed(): void {
        $sw = app(Stopwatch::class);
        $ts = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'status' => TimesheetStatus::Signed->value,
        ]);

        $this->expectException(\RuntimeException::class);
        $sw->start($this->user, $ts);
    }

    public function test_web_start_accepts_sqid_with_description_and_order(): void {
        $diary = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'title' => 'Wartungsauftrag',
        ]);

        $this->actingAs($this->user)
            ->post(route('stopwatch.start'), [
                'project_id' => $this->project->sqid,
                'description' => 'Doku',
                'diary_entry_id' => $diary->sqid,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('time_entries', [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'diary_entry_id' => $diary->id,
            'description' => 'Doku',
            'ended_at' => null,
        ]);
    }

    public function test_web_start_double_submit_flashes_error_instead_of_500(): void {
        $this->actingAs($this->user)
            ->post(route('stopwatch.start'), ['project_id' => $this->project->sqid])
            ->assertSessionHas('success');

        $this->actingAs($this->user)
            ->post(route('stopwatch.start'), ['project_id' => $this->project->sqid])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, TimeEntry::query()
            ->where('user_id', $this->user->id)
            ->whereNull('ended_at')
            ->count());
    }

    public function test_today_shows_running_entry_instead_of_idle_bar(): void {
        $ts = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'status' => TimesheetStatus::Draft->value,
        ]);
        app(Stopwatch::class)->start($this->user, $ts, null, 'Doku-Timer');

        $this->actingAs($this->user)
            ->get(route('today.show'))
            ->assertOk()
            ->assertSee('Doku-Timer')
            ->assertDontSee(__('Woran arbeitest du?'));
    }

    public function test_web_start_rejects_raw_integer_project_id(): void {
        // Sqid-Migration: rohe numerische IDs (wie sie ein echter HTTP-Request
        // als String liefert) werden nicht mehr akzeptiert.
        $this->actingAs($this->user)
            ->post(route('stopwatch.start'), ['project_id' => (string) $this->project->id])
            ->assertSessionHasErrors(['project_id']);

        $this->assertDatabaseCount('time_entries', 0);
    }
}
