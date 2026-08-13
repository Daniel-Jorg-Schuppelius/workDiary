<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlanApprovalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Enums\Shift\{DutyPlanStatus, ScheduledShiftStatus};
use App\Models\{DutyPlan, ScheduledShift, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Dienstplan-Genehmigungsworkflow + Archiv-Snapshot (MVP-525).
 */
class DutyPlanApprovalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $employee;

    private DutyPlan $plan;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->employee = $this->orgUser();

        $this->plan = DutyPlan::create([
            'organization_id' => $this->organization->id,
            'title' => 'KW 30',
            'period_type' => 'weekly',
            'from_date' => '2026-07-20',
            'to_date' => '2026-07-26',
            'status' => DutyPlanStatus::Draft->value,
            'created_by' => $this->admin->id,
        ]);
        ScheduledShift::create([
            'organization_id' => $this->organization->id,
            'duty_plan_id' => $this->plan->id,
            'user_id' => $this->employee->id,
            'date' => '2026-07-20',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => ScheduledShiftStatus::Draft->value,
        ]);
    }

    public function test_submit_and_approve_freezes_archive_snapshot(): void {
        $this->actingAs($this->admin)
            ->patch(route('duty-plans.submit', $this->plan))
            ->assertRedirect();
        $this->plan->refresh();
        $this->assertSame(DutyPlanStatus::Submitted, $this->plan->status);
        $this->assertSame($this->admin->id, $this->plan->submitted_by);

        $this->actingAs($this->admin)
            ->patch(route('duty-plans.approve', $this->plan))
            ->assertRedirect();
        $this->plan->refresh();
        $this->assertSame(DutyPlanStatus::Published, $this->plan->status);
        $this->assertSame($this->admin->id, $this->plan->approved_by);
        $this->assertNotNull($this->plan->archive_snapshot);
        $this->assertCount(1, $this->plan->archive_snapshot['shifts']);
        $this->assertSame('2026-07-20', $this->plan->archive_snapshot['shifts'][0]['date']);
    }

    public function test_direct_publish_also_freezes_snapshot(): void {
        $this->actingAs($this->admin)
            ->patch(route('duty-plans.publish', $this->plan))
            ->assertRedirect();

        $this->plan->refresh();
        $this->assertSame(DutyPlanStatus::Published, $this->plan->status);
        $this->assertNotNull($this->plan->archive_snapshot);
    }

    public function test_snapshot_survives_later_changes(): void {
        $this->actingAs($this->admin)->patch(route('duty-plans.publish', $this->plan));

        // Nachträgliche Änderung am Plan — der Snapshot bleibt unverändert.
        ScheduledShift::query()->update(['start_time' => '09:00']);

        $this->plan->refresh();
        $this->assertSame('08:00', substr((string) $this->plan->archive_snapshot['shifts'][0]['start_time'], 0, 5));
    }

    public function test_employee_cannot_approve(): void {
        $this->plan->update(['status' => DutyPlanStatus::Submitted]);

        $this->actingAs($this->employee)
            ->patch(route('duty-plans.approve', $this->plan))
            ->assertForbidden();
    }
}
