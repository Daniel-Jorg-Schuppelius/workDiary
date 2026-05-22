<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantBoundaryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Models\Event;
use App\Models\Milestone;
use App\Models\Organization;
use App\Models\PerDiemTrip;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Erweitert die OrganizationIsolationTest-Suite um weitere Kerngeschäftsmodelle.
 * Belegt für jedes Modell, dass eine Eloquent-Default-Query unter
 * Organization A keinen Datensatz aus Organization B sieht.
 *
 * Verbindet sich mit dem Audit unter docs/security/tenant-audit-2026.md.
 */
class TenantBoundaryTest extends TestCase {
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    private User $userB;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $this->orgA = Organization::factory()->create(['slug' => 'boundary-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'boundary-b']);

        $this->userA = User::factory()->user()->create(['organization_id' => $this->orgA->id]);
        $this->userB = User::factory()->user()->create(['organization_id' => $this->orgB->id]);
    }

    public function test_task_is_not_visible_cross_organization(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $taskB = $this->withOrg($this->orgB, fn() => Task::factory()->for($projectB)->create([
            'created_by' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $taskB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Task::find($taskB->id));
        $this->assertSame(0, Task::query()->count());
    }

    public function test_milestone_is_not_visible_cross_organization(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $milestoneB = $this->withOrg($this->orgB, fn() => Milestone::factory()->for($projectB)->create([
            'created_by' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $milestoneB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Milestone::find($milestoneB->id));
        $this->assertSame(0, Milestone::query()->count());
    }

    public function test_event_is_not_visible_cross_organization(): void {
        $eventB = $this->withOrg($this->orgB, fn() => Event::factory()->create([
            'organization_id' => $this->orgB->id,
            'responsible_user_id' => $this->userB->id,
        ]));

        $this->assertSame((int) $this->orgB->id, (int) $eventB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Event::find($eventB->id));
        $this->assertSame(0, Event::query()->count());
    }

    public function test_time_entry_is_not_visible_cross_organization(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $entryB = $this->withOrg($this->orgB, fn() => TimeEntry::factory()->for($projectB)->for($this->userB)->create());

        $this->assertSame((int) $this->orgB->id, (int) $entryB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(TimeEntry::find($entryB->id));
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_per_diem_trip_is_not_visible_cross_organization(): void {
        $tripB = $this->withOrg($this->orgB, fn() => PerDiemTrip::factory()->for($this->userB)->create());

        $this->assertSame((int) $this->orgB->id, (int) $tripB->organization_id);

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(PerDiemTrip::find($tripB->id));
        $this->assertSame(0, PerDiemTrip::query()->count());
    }

    public function test_timesheet_is_not_visible_cross_organization(): void {
        // Timesheet hat keine Factory – manuell anlegen.
        $timesheetB = $this->withOrg($this->orgB, function () {
            return Timesheet::create([
                'user_id' => $this->userB->id,
                'work_date' => now()->toDateString(),
                'kind' => \App\Enums\Timesheet\TimesheetKind::Project,
                'status' => \App\Enums\Timesheet\TimesheetStatus::Draft,
            ]);
        });

        $this->assertSame((int) $this->orgB->id, (int) $timesheetB->organization_id, 'Trait befüllt organization_id');

        app()->instance('currentOrganization', $this->orgA);
        $this->assertNull(Timesheet::find($timesheetB->id));
        $this->assertSame(0, Timesheet::query()->count());
    }

    public function test_cross_organization_update_is_blocked_by_scope(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $taskB = $this->withOrg($this->orgB, fn() => Task::factory()->for($projectB)->create([
            'created_by' => $this->userB->id,
        ]));

        app()->instance('currentOrganization', $this->orgA);
        // Eine Massen-Update-Query unter Org A darf den Datensatz aus Org B nicht treffen.
        $affected = Task::query()->where('id', $taskB->id)->update(['title' => 'hijacked']);
        $this->assertSame(0, $affected, 'Cross-Org-Update darf keine Zeilen treffen');

        // Originalwert muss erhalten bleiben.
        $reloaded = $this->withOrg($this->orgB, fn() => Task::find($taskB->id));
        $this->assertNotNull($reloaded);
        $this->assertNotSame('hijacked', $reloaded->title);
    }

    public function test_cross_organization_delete_is_blocked_by_scope(): void {
        $projectB = $this->withOrg($this->orgB, fn() => Project::factory()->create());
        $taskB = $this->withOrg($this->orgB, fn() => Task::factory()->for($projectB)->create([
            'created_by' => $this->userB->id,
        ]));

        app()->instance('currentOrganization', $this->orgA);
        $affected = Task::query()->where('id', $taskB->id)->delete();
        $this->assertSame(0, $affected, 'Cross-Org-Delete darf keine Zeilen treffen');

        $stillThere = $this->withOrg($this->orgB, fn() => Task::find($taskB->id));
        $this->assertNotNull($stillThere, 'Datensatz aus Org B darf nicht gelöscht worden sein');
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
