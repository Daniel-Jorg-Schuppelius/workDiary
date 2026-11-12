<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationRevisionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Absence;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\Vacation;
use App\Models\Platform\User;
use App\Services\Absence\WorkQueue\VacationRevisionWorkItemSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Urlaubsantrag „Korrektur anfordern“ (MVP-1554): zurückgeben, ändern, neu einreichen. */
class VacationRevisionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $approver;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->approver = $this->orgAdmin();
        $this->employee = $this->orgUser();
    }

    public function test_status_contract_knows_the_revision_loop(): void {
        $this->assertTrue(VacationStatus::Pending->canTransitionTo(VacationStatus::Revision));
        $this->assertTrue(VacationStatus::Revision->canTransitionTo(VacationStatus::Pending));
        $this->assertFalse(VacationStatus::Revision->canTransitionTo(VacationStatus::Approved));
        $this->assertFalse(VacationStatus::Approved->canTransitionTo(VacationStatus::Revision));
        $this->assertSame(__('enums.vacation.status.revision'), VacationStatus::Revision->label());
    }

    public function test_approver_returns_request_with_note_and_owner_resubmits(): void {
        $vacation = $this->pendingVacation();

        $this->actingAs($this->approver)->get(route('duties.index', ['tab' => 'urlaub']))->assertOk()
            ->assertSee(route('vacations.revision-form', $vacation), false);
        $this->actingAs($this->approver)->get(route('vacations.revision-form', $vacation) . '?dialog=1')->assertOk()
            ->assertSee('name="revision_note"', false);

        $this->actingAs($this->approver)->patch(route('vacations.revision', $vacation), ['revision_note' => ''])
            ->assertSessionHasErrors('revision_note');
        $this->actingAs($this->approver)->patch(route('vacations.revision', $vacation), ['revision_note' => 'Bitte erst ab dem 9. September.'])
            ->assertSessionHas('success');

        $vacation->refresh();
        $this->assertSame(VacationStatus::Revision, $vacation->status);
        $this->assertSame('Bitte erst ab dem 9. September.', $vacation->revision_note);

        // Entscheiden geht erst wieder nach der neuen Einreichung.
        $this->actingAs($this->approver)->patch(route('vacations.approve', $vacation))->assertSessionHas('error');
        $this->assertSame(VacationStatus::Revision, $vacation->fresh()?->status);

        $items = iterator_to_array(app(VacationRevisionWorkItemSource::class)->items($this->employee, $this->organization), false);
        $this->assertCount(1, $items);

        $this->actingAs($this->employee)->get(route('vacations.edit', $vacation) . '?dialog=1')->assertOk()
            ->assertSee('Bitte erst ab dem 9. September.')
            ->assertSee(__('vacation.revision.resubmit_hint'));
        $this->actingAs($this->employee)->put(route('vacations.update', $vacation), [
            'start_date' => now()->addDays(12)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'type' => VacationType::Vacation->value,
        ])->assertSessionHas('success', __('vacation.revision.resubmitted'));

        $vacation->refresh();
        $this->assertSame(VacationStatus::Pending, $vacation->status);
        $this->assertNull($vacation->revision_note);
        $this->assertSame(now()->addDays(12)->toDateString(), $vacation->start_date->toDateString());

        $this->actingAs($this->approver)->patch(route('vacations.approve', $vacation))->assertSessionHas('success');
        $this->assertSame(VacationStatus::Approved, $vacation->fresh()?->status);
    }

    public function test_owner_and_colleagues_cannot_return_a_request(): void {
        $vacation = $this->pendingVacation();

        $this->actingAs($this->employee)->patch(route('vacations.revision', $vacation), ['revision_note' => 'Eigener Antrag zurück.'])->assertForbidden();
        $this->actingAs($this->orgUser())->get(route('vacations.revision-form', $vacation))->assertForbidden();
        $this->assertSame(VacationStatus::Pending, $vacation->fresh()?->status);
    }

    public function test_no_return_after_the_first_approval_stage(): void {
        $this->organization->update(['settings' => ['vacation' => ['approval_stages' => 2]]]);
        app()->instance('currentOrganization', $this->organization->fresh());
        $vacation = $this->pendingVacation();
        $this->actingAs($this->approver)->patch(route('vacations.approve', $vacation))->assertRedirect();

        $this->actingAs($this->orgAdmin())->patch(route('vacations.revision', $vacation), ['revision_note' => 'Zu spät für eine Rückgabe.'])->assertForbidden();
    }

    public function test_returned_request_can_be_withdrawn(): void {
        $vacation = $this->pendingVacation();
        $vacation->update(['status' => VacationStatus::Revision, 'revision_note' => 'Zeitraum prüfen.']);

        $this->actingAs($this->employee)->patch(route('vacations.cancel', $vacation))->assertSessionHas('success');
        $this->assertSame(VacationStatus::Cancelled, $vacation->fresh()?->status);
    }

    private function pendingVacation(): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Pending->value,
        ]);
    }
}
