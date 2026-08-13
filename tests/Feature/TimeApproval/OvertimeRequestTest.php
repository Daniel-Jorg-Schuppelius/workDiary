<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OvertimeRequestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeApproval;

use App\Enums\Compliance\ComplianceFindingStatus;
use App\Enums\TimeApproval\OvertimeRequestStatus;
use App\Models\{ComplianceFinding, OvertimeRequest, User};
use App\Services\Compliance\AttendancePlausibilityScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Überstunden-Anträge (MVP-519): Einreichen, Entscheiden, Zurückziehen,
 * Mandantengrenze und Auto-Quittierung des Rahmenzeit-Befunds.
 */
class OvertimeRequestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $employee;

    private User $lead;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->employee = $this->orgUser();
        $this->lead = $this->userWithRole('teamleitung');
    }

    public function test_employee_can_submit_and_withdraw(): void {
        $this->actingAs($this->employee)
            ->post(route('overtime.store'), [
                'scope_date' => '2026-06-05',
                'minutes' => 90,
                'reason' => 'Inventurabschluss musste am selben Tag fertig werden.',
            ])
            ->assertRedirect(route('overtime.index'));

        $request = OvertimeRequest::query()->firstOrFail();
        $this->assertSame(OvertimeRequestStatus::Submitted, $request->status);
        $this->assertSame(90, $request->minutes);

        $this->actingAs($this->employee)
            ->post(route('overtime.withdraw', $request))
            ->assertRedirect();

        $this->assertSame(OvertimeRequestStatus::Withdrawn, $request->fresh()->status);
    }

    public function test_duplicate_open_request_for_same_day_is_rejected(): void {
        OvertimeRequest::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'requested_by_user_id' => $this->employee->id,
            'scope_date' => '2026-06-05',
        ]);

        $this->actingAs($this->employee)
            ->from(route('overtime.index'))
            ->post(route('overtime.store'), [
                'scope_date' => '2026-06-05',
                'minutes' => 30,
                'reason' => 'Noch ein Antrag für denselben Tag, sollte scheitern.',
            ])
            ->assertSessionHasErrors('scope_date');
    }

    public function test_lead_can_approve_and_frame_finding_is_acknowledged(): void {
        $request = OvertimeRequest::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'requested_by_user_id' => $this->employee->id,
            'scope_date' => '2026-06-05',
            'minutes' => 60,
        ]);
        $finding = ComplianceFinding::query()->create([
            'organization_id' => $this->organization->id,
            'category' => AttendancePlausibilityScanService::CATEGORY,
            'rule_code' => AttendancePlausibilityScanService::KIND_FRAME_TIME,
            'severity' => 'warning',
            'subject_type' => User::class,
            'subject_id' => $this->employee->id,
            'scope_date' => Carbon::parse('2026-06-05'),
            'detected_value' => 60,
            'threshold_value' => 15,
            'dedup_key' => 'plausibility|attendanceFrameTime|User#' . $this->employee->id . '|2026-06-05',
            'status' => ComplianceFindingStatus::Open,
        ]);

        $this->actingAs($this->lead)
            ->post(route('admin.overtime.approve', $request), ['note' => 'Betrieblich veranlasst.'])
            ->assertRedirect();

        $request->refresh();
        $this->assertSame(OvertimeRequestStatus::Approved, $request->status);
        $this->assertSame($this->lead->id, $request->decided_by_user_id);

        $finding->refresh();
        $this->assertSame(ComplianceFindingStatus::Accepted, $finding->status);
        $this->assertSame($this->lead->id, $finding->acknowledged_by);
    }

    public function test_employee_cannot_decide(): void {
        $request = OvertimeRequest::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'requested_by_user_id' => $this->employee->id,
        ]);

        $this->actingAs($this->employee)
            ->post(route('admin.overtime.approve', $request))
            ->assertForbidden();
    }

    public function test_foreign_org_request_is_not_visible_or_decidable(): void {
        $foreignRequest = OvertimeRequest::factory()->create([
            'organization_id' => \App\Models\Organization::factory()->create()->id,
        ]);

        // Sqid-Route-Bindung + Org-Scope: fremder Antrag ist nicht auffindbar.
        $this->actingAs($this->lead)
            ->post(route('admin.overtime.approve', $foreignRequest))
            ->assertNotFound();

        $this->actingAs($this->employee)
            ->get(route('overtime.index'))
            ->assertOk()
            ->assertDontSee($foreignRequest->reason);
    }

    public function test_rejection_keeps_finding_open(): void {
        $request = OvertimeRequest::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'requested_by_user_id' => $this->employee->id,
            'scope_date' => '2026-06-05',
        ]);
        ComplianceFinding::query()->create([
            'organization_id' => $this->organization->id,
            'category' => AttendancePlausibilityScanService::CATEGORY,
            'rule_code' => AttendancePlausibilityScanService::KIND_FRAME_TIME,
            'severity' => 'warning',
            'subject_type' => User::class,
            'subject_id' => $this->employee->id,
            'scope_date' => Carbon::parse('2026-06-05'),
            'detected_value' => 60,
            'threshold_value' => 15,
            'dedup_key' => 'plausibility|attendanceFrameTime|User#' . $this->employee->id . '|2026-06-05-b',
            'status' => ComplianceFindingStatus::Open,
        ]);

        $this->actingAs($this->lead)
            ->post(route('admin.overtime.reject', $request), ['note' => 'Nicht veranlasst.'])
            ->assertRedirect();

        $this->assertSame(OvertimeRequestStatus::Rejected, $request->fresh()->status);
        $this->assertSame(1, ComplianceFinding::query()->where('status', ComplianceFindingStatus::Open->value)->count());
    }
}
