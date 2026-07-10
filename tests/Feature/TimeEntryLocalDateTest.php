<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryLocalDateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Timesheet\{TimesheetKind, TimesheetStatus};
use App\Models\{Project, TimeEntry, Timesheet, User};
use App\Services\Timesheet\Stopwatch;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Whitebox 2026-07-10 (Z4): Der Kalendertag (`date`) eines Zeiteintrags wird
 * in der Anzeige-Zeitzone (Europe/Berlin) abgeleitet — nicht in UTC. Sonst
 * zählt eine Nachtschicht ab 00:30 lokal zum Vortag (Gleitzeit/Tages-
 * abschluss/Monatsrechnung). Gleiches Muster wie Attendance.
 */
class TimeEntryLocalDateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_saving_hook_derives_date_in_display_timezone(): void {
        // 09.07. 22:30 UTC = 10.07. 00:30 Europe/Berlin (CEST).
        $entry = TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'kind' => TimeEntryKind::Work->value,
            'started_at' => '2030-07-09 22:30:00',
            'ended_at' => '2030-07-09 23:30:00',
        ]);

        $this->assertSame('2030-07-10', $entry->fresh()->date?->toDateString());
    }

    public function test_stopwatch_start_uses_local_calendar_day(): void {
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Nachtprojekt',
            'status' => \App\Enums\Project\ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        $sheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'kind' => TimesheetKind::Project->value,
            'work_date' => '2030-07-10',
            'status' => TimesheetStatus::Draft->value,
        ]);

        // "Jetzt" = 09.07. 22:30 UTC = 10.07. 00:30 lokal.
        $this->travelTo(CarbonImmutable::parse('2030-07-09 22:30:00', 'UTC'));

        $entry = app(Stopwatch::class)->start($this->user, $sheet);

        $this->assertSame('2030-07-10', $entry->fresh()->date?->toDateString());
    }
}
