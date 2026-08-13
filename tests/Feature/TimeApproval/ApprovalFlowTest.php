<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalFlowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeApproval;

use App\Enums\Approval\ApprovalDecision;
use App\Enums\TimeApproval\{OvertimeRequestStatus, TimeCorrectionStatus};
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{ApprovalStep, Project, TimeEntry, User, Vacation};
use App\Services\TimeApproval\{OvertimeRequestService, TimeCorrectionService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-531: generisches Antragsverfahren-Framework — konfigurierbare
 * Genehmigungsstufen je Antragstyp, Vier-Augen über approval_steps.
 */
class ApprovalFlowTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    /** @param array<string, mixed> $settings */
    private function withSettings(array $settings): void {
        $this->organization->update(['settings' => $settings]);
        app()->instance('currentOrganization', $this->organization->fresh());
    }

    private function pendingVacation(User $employee): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $employee->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-11',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Pending->value,
        ]);
    }

    // ── Urlaub (bestehender Flow schreibt jetzt Steps) ───────────────────

    public function test_vacation_two_stage_writes_approval_steps(): void {
        $this->withSettings(['vacation' => ['approval_stages' => 2]]);
        $adminOne = $this->orgAdmin();
        $adminTwo = $this->orgAdmin();
        $vacation = $this->pendingVacation($this->orgUser());

        $this->actingAs($adminOne)->patch(route('vacations.approve', $vacation))->assertRedirect();
        $this->assertSame(VacationStatus::Pending, $vacation->fresh()->status);

        $this->actingAs($adminTwo)->patch(route('vacations.approve', $vacation))->assertRedirect();
        $this->assertSame(VacationStatus::Approved, $vacation->fresh()->status);

        $steps = ApprovalStep::query()
            ->where('approvable_type', Vacation::class)
            ->where('approvable_id', $vacation->id)
            ->orderBy('stage')
            ->get();
        $this->assertCount(2, $steps);
        $this->assertSame([1, 2], $steps->pluck('stage')->all());
        $this->assertSame([$adminOne->id, $adminTwo->id], $steps->pluck('decided_by')->all());
    }

    public function test_vacation_legacy_first_approval_is_backfilled(): void {
        $this->withSettings(['vacation' => ['approval_stages' => 2]]);
        $adminOne = $this->orgAdmin();
        $adminTwo = $this->orgAdmin();

        // Alt-Antrag: erste Freigabe stammt aus der Zeit vor dem Framework.
        $vacation = $this->pendingVacation($this->orgUser());
        $vacation->update([
            'first_approved_by' => $adminOne->id,
            'first_approved_at' => now()->subDay(),
        ]);

        // Vier-Augen greift auch über den Backfill.
        $this->actingAs($adminOne)
            ->patch(route('vacations.approve', $vacation))
            ->assertSessionHas('error');
        $this->assertSame(VacationStatus::Pending, $vacation->fresh()->status);

        $this->actingAs($adminTwo)->patch(route('vacations.approve', $vacation))->assertRedirect();
        $this->assertSame(VacationStatus::Approved, $vacation->fresh()->status);
        $this->assertSame(2, ApprovalStep::query()
            ->where('approvable_type', Vacation::class)
            ->where('approvable_id', $vacation->id)
            ->count());
    }

    // ── Überstunden-Antrag (MVP-519) zweistufig ──────────────────────────

    public function test_overtime_two_stage_requires_distinct_deciders(): void {
        $this->withSettings(['approvals' => ['overtime_stages' => 2]]);
        $owner = $this->orgUser();
        $adminOne = $this->orgAdmin();
        $adminTwo = $this->orgAdmin();

        $svc = app(OvertimeRequestService::class);
        $request = $svc->submit($owner, $owner, CarbonImmutable::parse('2026-06-10'), 60, 'Inventur');

        // Erste Stufe: Antrag bleibt offen.
        $svc->decide($request->fresh(), $adminOne, true);
        $this->assertSame(OvertimeRequestStatus::Submitted, $request->fresh()->status);

        // Dieselbe Person darf nicht final entscheiden.
        try {
            $svc->decide($request->fresh(), $adminOne, true);
            $this->fail('Vier-Augen-Verstoß wurde nicht abgefangen.');
        } catch (ValidationException) {
            // erwartet
        }

        $svc->decide($request->fresh(), $adminTwo, true);
        $this->assertSame(OvertimeRequestStatus::Approved, $request->fresh()->status);
    }

    public function test_overtime_rejection_is_final_on_first_stage(): void {
        $this->withSettings(['approvals' => ['overtime_stages' => 2]]);
        $owner = $this->orgUser();

        $svc = app(OvertimeRequestService::class);
        $request = $svc->submit($owner, $owner, CarbonImmutable::parse('2026-06-11'), 30, 'Abend-Deployment');

        $svc->decide($request->fresh(), $this->orgAdmin(), false, 'Nicht veranlasst');
        $this->assertSame(OvertimeRequestStatus::Rejected, $request->fresh()->status);

        $step = ApprovalStep::query()
            ->where('approvable_type', \App\Models\OvertimeRequest::class)
            ->where('approvable_id', $request->id)
            ->sole();
        $this->assertSame(ApprovalDecision::Rejected, $step->decision);
    }

    // ── Zeitkorrektur (Stempel-Selbstkorrektur) zweistufig ───────────────

    public function test_time_correction_two_stage_flow(): void {
        $this->withSettings(['approvals' => ['time_correction_stages' => 2]]);
        $user = $this->orgUser();
        $adminOne = $this->orgAdmin();
        $adminTwo = $this->orgAdmin();

        /** @var Project $project */
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        /** @var TimeEntry $entry */
        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'date' => '2026-06-10',
            'minutes' => 60,
        ]);

        $svc = app(TimeCorrectionService::class);
        $request = $svc->createDraft(
            $user,
            CarbonImmutable::parse('2026-06-10'),
            'Minuten nachtragen wegen vergessener Stempelung',
            [[
                'target_type' => TimeEntry::class,
                'target_id' => $entry->id,
                'action' => 'update',
                'before' => ['minutes' => 60],
                'after' => ['minutes' => 90],
            ]],
            $user,
        );
        $svc->submit($request, $user);

        // Erste Stufe: bleibt eingereicht.
        $svc->approve($request->fresh(), $adminOne);
        $this->assertSame(TimeCorrectionStatus::Submitted, $request->fresh()->status);

        $svc->approve($request->fresh(), $adminTwo);
        $this->assertSame(TimeCorrectionStatus::Approved, $request->fresh()->status);
    }
}
