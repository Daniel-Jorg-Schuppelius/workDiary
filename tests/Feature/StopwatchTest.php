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
use App\Models\{Project, Timesheet, User};
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
}
