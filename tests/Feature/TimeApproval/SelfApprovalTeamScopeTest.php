<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfApprovalTeamScopeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\TimeApproval;

use App\Enums\TimeApproval\{DayClosureStatus, MonthClosureStatus, TimeCorrectionStatus};
use App\Enums\User\Permission as P;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\Vacation;
use App\Models\Audit\AuditLog;
use App\Models\Diary\DayClosure;
use App\Models\Platform\User;
use App\Models\Time\{MonthClosure, TimeCorrectionRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entscheidungen 2026-10-23: keine Entscheidung über den eigenen Urlaub, Monat
 * oder Tag — auch nicht als Admin —, außer niemand sonst darf entscheiden (dann
 * mit Audit-Vermerk); Team-Rechte zeigen nur das eigene Team.
 */
class SelfApprovalTeamScopeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @param list<P> $permissions */
    private function member(array $permissions = []): User {
        $user = $this->orgUser();
        if ($permissions !== []) {
            $user->givePermissionTo(array_map(static fn (P $p): string => $p->value, $permissions));
            $user->unsetRelation('permissions');
        }

        return $user;
    }

    private function vacation(User $owner, VacationStatus $status = VacationStatus::Pending): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id, 'user_id' => $owner->id, 'start_date' => '2030-06-01', 'end_date' => '2030-06-05',
            'type' => VacationType::Vacation->value, 'status' => $status->value,
        ]);
    }

    private function month(User $owner): MonthClosure {
        return MonthClosure::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $owner->id, 'period_year' => 2024, 'period_month' => 1,
            'status' => MonthClosureStatus::Submitted, 'days_total' => 31, 'days_with_attendance' => 0, 'days_closed' => 0, 'days_open' => 0, 'warnings_count' => 0,
        ]);
    }

    private function correction(User $owner): TimeCorrectionRequest {
        return TimeCorrectionRequest::query()->create([
            'organization_id' => $this->organization->id, 'user_id' => $owner->id, 'requested_by_user_id' => $owner->id,
            'scope_date' => '2026-06-10', 'status' => TimeCorrectionStatus::Submitted, 'reason' => 'Stempel vergessen',
        ]);
    }

    private function selfDecisionAudits(string $event): int {
        return AuditLog::query()->where('event', $event)->count();
    }

    public function test_admin_does_not_decide_own_vacation_while_another_approver_exists(): void {
        $admin = $this->orgAdmin();
        $otherAdmin = $this->orgAdmin();
        $pending = $this->vacation($admin);
        $rejected = $this->vacation($admin, VacationStatus::Rejected);

        $this->assertFalse(Gate::forUser($admin)->allows('decide', $pending));
        $this->assertTrue(Gate::forUser($otherAdmin)->allows('decide', $pending));
        $this->actingAs($admin)->patch(route('vacations.reject', $pending))->assertForbidden();
        $this->actingAs($admin)->patch(route('vacations.approve', $rejected))->assertForbidden();
        $this->assertSame(VacationStatus::Rejected, $rejected->refresh()->status);
    }

    public function test_sole_approver_decides_own_vacation_with_audit_note(): void {
        $admin = $this->orgAdmin();
        $this->member();
        $vacation = $this->vacation($admin);

        $this->actingAs($admin)->patch(route('vacations.approve', $vacation))->assertRedirect();

        $this->assertSame(VacationStatus::Approved, $vacation->refresh()->status);
        $this->assertSame(1, $this->selfDecisionAudits('vacation.self_decided'));
    }

    public function test_nobody_approves_own_month_while_another_approver_exists(): void {
        $admin = $this->orgAdmin();
        $approver = $this->member([P::MonthApprove, P::MonthReject, P::MonthViewOrganization]);

        $this->assertFalse(Gate::forUser($approver)->allows('approve', $this->month($approver)));
        $adminMonth = $this->month($admin);
        $this->assertFalse(Gate::forUser($admin)->allows('approve', $adminMonth));
        $this->assertFalse(Gate::forUser($admin)->allows('reject', $adminMonth));
        $this->assertTrue(Gate::forUser($approver)->allows('approve', $adminMonth));
    }

    public function test_sole_admin_approves_own_month_with_audit_note(): void {
        $admin = $this->orgAdmin();
        $closure = $this->month($admin);

        $this->actingAs($admin)->post(route('admin.month-approval.approve', $closure))->assertRedirect();

        $this->assertSame(MonthClosureStatus::Approved, $closure->refresh()->status);
        $this->assertSame(1, $this->selfDecisionAudits('month_closure.self_decided'));
    }

    public function test_own_day_correction_is_not_decided_while_another_approver_exists(): void {
        $this->orgAdmin();
        $lead = $this->member([P::DayCloseApproveCorrection, P::DayCloseViewOrganization]);
        $closure = DayClosure::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $lead->id, 'status' => DayClosureStatus::Correction->value]);

        $this->assertFalse(Gate::forUser($lead)->allows('approveCorrection', $closure));
    }

    public function test_month_team_right_covers_own_team_only(): void {
        $lead = $this->member([P::MonthViewTeam, P::MonthApprove]);
        $mate = $this->orgUser(['name' => 'Tina Teamkollegin']);
        $outsider = $this->orgUser(['name' => 'Olaf Andersteam']);
        $this->sameTeam($lead, $mate);
        $mateMonth = $this->month($mate);
        $outsiderMonth = $this->month($outsider);

        $this->assertTrue(Gate::forUser($lead)->allows('approve', $mateMonth));
        $this->assertFalse(Gate::forUser($lead)->allows('approve', $outsiderMonth));
        $this->assertFalse(Gate::forUser($lead)->allows('view', $outsiderMonth));
        $this->actingAs($lead)->get(route('admin.month-approval.index'))
            ->assertOk()->assertSee('Tina Teamkollegin')->assertDontSee('Olaf Andersteam');

        $controller = $this->member([P::MonthViewOrganization, P::MonthApprove]);
        $this->assertTrue(Gate::forUser($controller)->allows('approve', $outsiderMonth));
    }

    public function test_correction_team_right_covers_own_team_only(): void {
        $lead = $this->member([P::CorrectionViewTeam, P::CorrectionApprove]);
        $mate = $this->orgUser(['name' => 'Tina Teamkollegin']);
        $outsider = $this->orgUser(['name' => 'Olaf Andersteam']);
        $this->sameTeam($lead, $mate);

        $this->assertTrue(Gate::forUser($lead)->allows('approve', $this->correction($mate)));
        $this->assertFalse(Gate::forUser($lead)->allows('approve', $this->correction($outsider)));
        $this->actingAs($lead)->get(route('admin.corrections.index'))
            ->assertOk()->assertSee('Tina Teamkollegin')->assertDontSee('Olaf Andersteam');
    }
}
