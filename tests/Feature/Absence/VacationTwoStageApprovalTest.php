<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationTwoStageApprovalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Absence;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{User, Vacation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zweistufige Urlaubs-Genehmigung + Stellvertreter-Regelung (MVP-523).
 */
class VacationTwoStageApprovalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $adminOne;

    private User $adminTwo;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->adminOne = $this->orgAdmin();
        $this->adminTwo = $this->orgAdmin();
        $this->employee = $this->orgUser();
    }

    private function pendingVacation(): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-11',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Pending->value,
        ]);
    }

    private function enableTwoStage(): void {
        $this->organization->update(['settings' => ['vacation' => ['approval_stages' => 2]]]);
        app()->instance('currentOrganization', $this->organization->fresh());
    }

    public function test_single_stage_approves_directly(): void {
        $vacation = $this->pendingVacation();

        $this->actingAs($this->adminOne)
            ->patch(route('vacations.approve', $vacation))
            ->assertRedirect();

        $this->assertSame(VacationStatus::Approved, $vacation->fresh()->status);
    }

    public function test_two_stage_requires_two_distinct_approvers(): void {
        $this->enableTwoStage();
        $vacation = $this->pendingVacation();

        // Erste Freigabe: Status bleibt Pending.
        $this->actingAs($this->adminOne)
            ->patch(route('vacations.approve', $vacation))
            ->assertRedirect();
        $vacation->refresh();
        $this->assertSame(VacationStatus::Pending, $vacation->status);
        $this->assertSame($this->adminOne->id, $vacation->first_approved_by);

        // Dieselbe Person darf nicht final freigeben.
        $this->actingAs($this->adminOne)
            ->patch(route('vacations.approve', $vacation))
            ->assertSessionHas('error');
        $this->assertSame(VacationStatus::Pending, $vacation->fresh()->status);

        // Zweite Person gibt final frei.
        $this->actingAs($this->adminTwo)
            ->patch(route('vacations.approve', $vacation))
            ->assertRedirect();
        $vacation->refresh();
        $this->assertSame(VacationStatus::Approved, $vacation->status);
        $this->assertSame($this->adminTwo->id, $vacation->decided_by);
    }

    public function test_deputy_can_decide_only_while_principal_absent(): void {
        $deputy = $this->orgUser();
        $this->adminOne->update(['deputy_user_id' => $deputy->id]);
        $this->adminTwo->delete();

        $vacation = $this->pendingVacation();

        // Ohne Abwesenheit des Vertretenen: kein Entscheidungsrecht.
        $this->actingAs($deputy)
            ->patch(route('vacations.approve', $vacation))
            ->assertForbidden();

        // Vertretener ist selbst im genehmigten Urlaub → Deputy entscheidet.
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminOne->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);

        $this->actingAs($deputy)
            ->patch(route('vacations.approve', $vacation))
            ->assertRedirect();
        $this->assertSame(VacationStatus::Approved, $vacation->fresh()->status);
    }
}
