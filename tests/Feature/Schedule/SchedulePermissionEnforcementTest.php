<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulePermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\User\{Permission, UserRole};
use App\Models\Diary\{EmergencyAssignment, OnCallShift};
use App\Models\Schedule\{CoverageRequirement, DutyPlan, ScheduledShift, ShiftType};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Dienstplan, Schichten, Schichttypen, Soll-Besetzung, Bereitschaft und
 * Notdienst prüfen ihre Verwaltungsrechte (lk-06): Teamleitung darf laut
 * Rollenmatrix, Mitarbeitende nicht, Admins immer.
 */
class SchedulePermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_duty_plans_follow_their_rights(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $worker = $this->userWithRole(UserRole::User->value);
        $admin = $this->orgAdmin();
        $plan = DutyPlan::factory()->create(['organization_id' => $this->organization->id]);

        foreach (['update', 'publish'] as $ability) {
            $this->assertTrue(Gate::forUser($lead)->allows($ability, $plan), $ability);
            $this->assertFalse(Gate::forUser($worker)->allows($ability, $plan), $ability);
            $this->assertTrue(Gate::forUser($admin)->allows($ability, $plan), $ability);
        }
        $this->assertTrue(Gate::forUser($lead)->allows('create', DutyPlan::class));
        $this->assertFalse(Gate::forUser($worker)->allows('create', DutyPlan::class));

        $this->assertFalse(Gate::forUser($lead)->allows('delete', $plan));
        $this->assertTrue(Gate::forUser($this->actorIn($this->organization, [Permission::DutyPlanDelete]))->allows('delete', $plan));
        $this->assertFalse(Gate::forUser($lead)->allows('approve', $plan), 'Genehmigen bleibt Admin');

        $this->actingAs($worker)->patch(route('duty-plans.publish', $plan))->assertForbidden();
        $this->actingAs($lead)->patch(route('duty-plans.publish', $plan))->assertRedirect();
    }

    public function test_scheduled_shifts_shift_types_and_coverage_follow_their_rights(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $worker = $this->userWithRole(UserRole::User->value);
        $type = ShiftType::factory()->create(['organization_id' => $this->organization->id]);
        $shift = ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $worker->id,
            'shift_type_id' => $type->id,
            'status' => ScheduledShiftStatus::Draft,
        ]);

        $this->assertTrue(Gate::forUser($lead)->allows('publish', $shift));
        $this->assertFalse(Gate::forUser($worker)->allows('update', $shift));
        $this->actingAs($worker)->patch(route('schedule.shifts.publish', $shift))->assertForbidden();
        $this->actingAs($lead)->patch(route('schedule.shifts.publish', $shift))->assertOk();
        $this->assertSame(ScheduledShiftStatus::Published, $shift->refresh()->status);

        $this->actingAs($lead)->get(route('shift-types.index'))->assertOk();
        $this->actingAs($worker)->get(route('shift-types.index'))->assertForbidden();
        $this->actingAs($this->orgAdmin())->get(route('shift-types.index'))->assertOk();

        $this->assertTrue(Gate::forUser($lead)->allows('create', CoverageRequirement::class));
        $this->assertFalse(Gate::forUser($worker)->allows('create', CoverageRequirement::class));
    }

    public function test_on_call_and_emergency_management_follow_their_rights(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $worker = $this->userWithRole(UserRole::User->value);
        $shift = OnCallShift::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $worker->id]);
        $assignment = EmergencyAssignment::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $worker->id]);

        $this->assertTrue(Gate::forUser($lead)->allows('update', $shift));
        $this->assertTrue(Gate::forUser($worker)->allows('update', $shift), 'Eigentümer');
        $this->assertFalse(Gate::forUser($accounting)->allows('update', $shift));
        $this->assertTrue(Gate::forUser($lead)->allows('delete', $assignment));
        $this->assertFalse(Gate::forUser($accounting)->allows('delete', $assignment));

        $this->actingAs($lead)->get(route('shifts.edit', $shift))->assertOk();
        $this->actingAs($accounting)->get(route('shifts.edit', $shift))->assertForbidden();
        $this->actingAs($lead)->get(route('assignments.edit', $assignment))->assertOk();
        $this->actingAs($accounting)->get(route('assignments.edit', $assignment))->assertForbidden();
        $this->actingAs($this->orgAdmin())->get(route('assignments.edit', $assignment))->assertOk();
    }
}
