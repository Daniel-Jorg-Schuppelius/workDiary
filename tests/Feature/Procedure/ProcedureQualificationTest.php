<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureQualificationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Exceptions\{ProcedureSecondPersonException, ProcedureStepBlockedException};
use App\Models\Hr\{Qualification, UserQualification};
use App\Models\Platform\User;
use App\Services\Procedure\{ProcedureExecutionService, SecondPersonGate};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Procedure\Concerns\BuildsProcedureRuns;
use Tests\TestCase;

/** Qualifikation am Schritt und für die zweite Person (MVP-1568). */
class ProcedureQualificationTest extends TestCase {
    use BuildsProcedureRuns;
    use RefreshDatabase;

    public function test_step_needs_a_currently_valid_qualification_of_the_acting_person(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [['code' => 'schalten', 'required_qualification_code' => 'EFK']]), $lead);
        $step = $this->stepOf($run, 'schalten');
        $execution = app(ProcedureExecutionService::class);

        $this->assertSame(ProcedureStepBlockedException::REASON_MISSING_QUALIFICATION, $execution->blockReasonFor($step, $lead));

        $held = $this->grant($lead, 'efk', now()->subYear(), now()->subDay());
        $this->assertSame(ProcedureStepBlockedException::REASON_MISSING_QUALIFICATION, $execution->blockReasonFor($step, $lead), 'abgelaufen');

        $held->update(['valid_from' => now()->addDay(), 'valid_until' => null]);
        $this->assertSame(ProcedureStepBlockedException::REASON_MISSING_QUALIFICATION, $execution->blockReasonFor($step, $lead), 'noch nicht gültig');

        $held->update(['valid_from' => now()->subDay(), 'valid_until' => now()->addMonth()]);
        $this->assertNull($execution->blockReasonFor($step, $lead));

        // Die Qualifikation einer anderen Organisation mit demselben Kürzel zählt nicht.
        $stranger = User::factory()->teamleitung()->create();
        $this->grant($stranger, 'EFK');
        $this->assertSame(ProcedureStepBlockedException::REASON_MISSING_QUALIFICATION, $execution->blockReasonFor($step, $stranger));

        $this->actingAs($lead)->post(route('procedure-runs.steps.execute', [$run, $step]), ['status' => 'done'])->assertSessionHas('success');
    }

    public function test_second_person_needs_the_minimum_qualification(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [
            ['code' => 'freigabe', 'requires_second_person' => true, 'config' => ['second_person_min_qualification' => 'Prüfer Stufe 2']],
        ]), $lead);
        $step = $this->stepOf($run, 'freigabe');
        $gate = app(SecondPersonGate::class);
        $second = $this->colleagueOf($lead);

        try {
            $gate->take($step, $second);
            $this->fail('Expected ProcedureSecondPersonException');
        } catch (ProcedureSecondPersonException $e) {
            $this->assertSame(ProcedureSecondPersonException::REASON_QUALIFICATION_MISSING, $e->reason);
        }
        $this->actingAs($second)->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.steps.second-person', [$run, $step]))
            ->assertSessionHas('error', __('procedure.secondPerson.reason.qualificationMissing'));

        // Ohne Kürzel greift der Name der Qualifikation.
        $this->grant($second, null, null, null, 'Prüfer Stufe 2');
        $this->actingAs($second)->post(route('procedure-runs.steps.second-person', [$run, $step]))->assertSessionHas('success');
        $this->assertNotNull($step->fresh()->second_person_signed_at);
    }

    private function grant(User $user, ?string $abbreviation, mixed $from = null, mixed $until = null, string $name = 'Elektrofachkraft'): UserQualification {
        $qualification = Qualification::factory()->create([
            'organization_id' => $user->organization_id,
            'name' => $name,
            'abbreviation' => $abbreviation,
        ]);

        return UserQualification::query()->create([
            'user_id' => $user->id,
            'qualification_id' => $qualification->id,
            'valid_from' => $from,
            'valid_until' => $until,
        ]);
    }
}
