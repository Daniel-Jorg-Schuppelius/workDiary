<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimePermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Time;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Enums\User\{Permission, UserRole};
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\Time\{TimeEntry, Timesheet};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Zeiteinträge und Stundenzettel prüfen das Recht (lk-06): Rollen mit dem
 * Recht dürfen, Konten ohne nicht, Admins immer. Eigentum und Status gelten
 * zusätzlich.
 */
class TimePermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Rechte-Projekt',
            'status' => ProjectStatus::Active->value,
        ]);
    }

    public function test_own_time_entries_follow_the_rights_of_the_role(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $support = $this->userWithRole(UserRole::Support->value);
        $bare = $this->actorIn($this->organization, [Permission::TimeEntryViewOwn]);
        $admin = $this->orgAdmin();

        $this->assertTrue(Gate::forUser($worker)->allows('create', TimeEntry::class));
        $this->assertFalse(Gate::forUser($support)->allows('create', TimeEntry::class));
        $this->assertFalse(Gate::forUser($bare)->allows('create', TimeEntry::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', TimeEntry::class));

        $own = $this->entryFor($bare);
        $this->assertTrue(Gate::forUser($bare)->allows('view', $own));
        $this->assertFalse(Gate::forUser($bare)->allows('update', $own));
        $this->assertFalse(Gate::forUser($bare)->allows('delete', $own));

        $mine = $this->entryFor($worker);
        $this->assertTrue(Gate::forUser($worker)->allows('update', $mine));
        $this->assertTrue(Gate::forUser($worker)->allows('delete', $mine));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $mine));

        $this->actingAs($support)
            ->get(route('admin-time-entries.create'))
            ->assertForbidden();
    }

    public function test_every_internal_profile_keeps_recording_its_own_time(): void {
        foreach ([UserRole::Geschaeftsfuehrung, UserRole::Personalverwaltung, UserRole::Teamleitung, UserRole::Buchhaltung, UserRole::Callcenter, UserRole::Aussendienst] as $role) {
            $user = $this->userWithRole($role->value);
            $this->assertTrue(Gate::forUser($user)->allows('create', TimeEntry::class), $role->value);
            $this->assertTrue(Gate::forUser($user)->allows('create', Timesheet::class), $role->value);
            $this->assertTrue(Gate::forUser($user)->allows('view', $this->entryFor($user)), $role->value);
        }
    }

    public function test_own_timesheets_need_the_matching_right(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $readOnly = $this->actorIn($this->organization, [Permission::TimesheetCreate]);

        $sheet = $this->sheetFor($worker);
        $this->assertTrue(Gate::forUser($worker)->allows('update', $sheet));
        $this->assertTrue(Gate::forUser($worker)->allows('delete', $sheet));
        $this->assertTrue(Gate::forUser($worker)->allows('sign', $sheet));

        $foreignRights = $this->sheetFor($readOnly);
        $this->assertTrue(Gate::forUser($readOnly)->allows('create', Timesheet::class));
        $this->assertFalse(Gate::forUser($readOnly)->allows('update', $foreignRights));
        $this->assertFalse(Gate::forUser($readOnly)->allows('delete', $foreignRights));
        $this->assertFalse(Gate::forUser($readOnly)->allows('sign', $foreignRights));

        $this->actingAs($this->userWithRole(UserRole::Support->value))
            ->post(route('projects.timesheets.store', $this->project), ['work_date' => '2030-02-01'])
            ->assertForbidden();
    }

    public function test_locking_follows_the_lock_rights(): void {
        $owner = $this->userWithRole(UserRole::User->value);
        $sheet = $this->sheetFor($owner, ['status' => TimesheetStatus::Signed->value]);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);

        $this->assertTrue(Gate::forUser($lead)->allows('lock', $sheet));
        $this->assertTrue(Gate::forUser($accounting)->allows('unlock', $sheet));
        $this->assertFalse(Gate::forUser($owner)->allows('lock', $sheet));
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('lock', $sheet));

        $this->actingAs($owner)
            ->post(route('projects.timesheets.lock', [$this->project, $sheet]))
            ->assertForbidden();
        $this->actingAs($lead)
            ->post(route('projects.timesheets.lock', [$this->project, $sheet]))
            ->assertRedirect();
        $this->assertTrue($sheet->refresh()->isLocked());

        $other = Organization::factory()->create();
        $foreignLead = $this->userWithRole(UserRole::Teamleitung->value, $other);
        $this->assertFalse(Gate::forUser($foreignLead)->allows('unlock', $sheet));
    }

    public function test_foreign_timesheets_need_timesheet_view_any(): void {
        $owner = $this->userWithRole(UserRole::User->value);
        $sheet = $this->sheetFor($owner);

        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::Teamleitung->value))->allows('view', $sheet));
        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::Support->value))->allows('view', $sheet));
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::User->value))->allows('view', $sheet));
        $this->assertFalse(Gate::forUser($this->actorIn($this->organization, [Permission::TimeEntryViewAny]))->allows('view', $sheet));
    }

    private function entryFor(User $user): TimeEntry {
        return TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $user->id,
            'date' => now()->toDateString(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function sheetFor(User $user, array $attributes = []): Timesheet {
        return Timesheet::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $user->id,
            'work_date' => '2030-02-15',
            'status' => TimesheetStatus::Draft->value,
        ], $attributes));
    }
}
