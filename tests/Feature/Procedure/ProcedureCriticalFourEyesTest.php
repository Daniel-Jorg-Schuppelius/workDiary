<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureCriticalFourEyesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureRiskLevel, ProcedureRunEventType, ProcedureRunStatus, ProcedureStepRunStatus, ProcedureStepType};
use App\Exceptions\ProcedureRunIncompleteException;
use App\Services\Procedure\{ProcedureExecutionService, SecondPersonGate};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Procedure\Concerns\BuildsProcedureRuns;
use Tests\TestCase;

/** Kritische Vorlagen verlangen einen Vier-Augen-Schritt (MVP-1570). */
class ProcedureCriticalFourEyesTest extends TestCase {
    use BuildsProcedureRuns;
    use RefreshDatabase;

    public function test_critical_version_without_four_eyes_step_is_not_published(): void {
        $lead = $this->lead();
        $template = $this->draftTemplate($lead, [['code' => 'a']], ['risk_level' => ProcedureRiskLevel::Critical]);
        $draft = $this->draftOf($template);

        $this->actingAs($lead)->from(route('procedures.edit', $template))
            ->post(route('procedures.versions.publish', [$template, $draft]))
            ->assertSessionHasErrors(['risk_level' => __('procedure.validation.criticalNeedsSecondPerson')]);
        $this->assertNull($draft->fresh()->published_at);

        $this->actingAs($lead)->put(route('procedures.update', $template), [
            'name' => 'Prüfablauf', 'risk_level' => 'critical',
            'steps' => [
                ['code' => 'a', 'step_type' => 'confirm', 'label' => 'Arbeit'],
                ['code' => 'frei', 'step_type' => ProcedureStepType::Freigabe->value, 'label' => 'Freigabe'],
            ],
        ])->assertSessionHasNoErrors();
        $this->actingAs($lead)->post(route('procedures.versions.publish', [$template, $draft]))->assertSessionHasNoErrors();
        $this->assertNotNull($draft->fresh()->published_at);
    }

    public function test_critical_run_completes_only_with_a_countersigned_step(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [
            ['code' => 'arbeit'],
            ['code' => 'frei', 'requires_second_person' => true, 'required' => false, 'blocking' => false],
        ], ['risk_level' => ProcedureRiskLevel::Critical]), $lead);
        $execution = app(ProcedureExecutionService::class);
        $execution->execute($this->stepOf($run, 'arbeit'), $lead, ProcedureStepRunStatus::Done);

        // Alle Pflichtschritte sind erledigt, die Freigabe fehlt.
        $this->assertSame([], $execution->missingRequiredStepRuns($run));
        try {
            $execution->completeRun($run->fresh(), $lead);
            $this->fail('Expected ProcedureRunIncompleteException');
        } catch (ProcedureRunIncompleteException $e) {
            $this->assertSame(ProcedureExecutionService::REASON_CRITICAL_SECOND_PERSON, $e->reason);
        }
        $rejected = $run->journal()->reorder()->where('event_type', ProcedureRunEventType::RunCompletionRejected->value)->firstOrFail();
        $this->assertSame(ProcedureExecutionService::REASON_CRITICAL_SECOND_PERSON, data_get($rejected->payload, 'reason'));

        $this->actingAs($lead)->get(route('procedure-runs.show', $run))->assertOk()->assertSee(__('procedure.validation.criticalSecondPersonMissing'));
        $this->actingAs($lead)->from(route('procedure-runs.show', $run))->post(route('procedure-runs.complete', $run))
            ->assertSessionHas('error', __('procedure.validation.criticalSecondPersonMissing'));

        $frei = $this->stepOf($run, 'frei');
        $gate = app(SecondPersonGate::class);
        $second = $this->colleagueOf($lead);
        $gate->request($frei, $lead);
        $gate->take($frei->fresh(), $second);
        $gate->sign($frei->fresh(), $second);
        $execution->execute($frei->fresh(), $lead, ProcedureStepRunStatus::Done);

        $this->assertSame(ProcedureRunStatus::Completed, $execution->completeRun($run->fresh(), $lead)->status);
    }

    public function test_normal_run_and_legacy_critical_version_without_four_eyes_step_stay_completable(): void {
        $lead = $this->lead();
        $execution = app(ProcedureExecutionService::class);

        $normal = $this->startRun($this->publishedTemplate($lead, [['code' => 'a'], ['code' => 'frei', 'requires_second_person' => true, 'required' => false, 'blocking' => false]]), $lead);
        $execution->execute($this->stepOf($normal, 'a'), $lead, ProcedureStepRunStatus::Done);
        $this->assertSame(ProcedureRunStatus::Completed, $execution->completeRun($normal->fresh(), $lead)->status);

        // Vor der Veröffentlichungsprüfung entstandene kritische Version ohne Vier-Augen-Schritt.
        $template = $this->publishedTemplate($lead, [['code' => 'a']]);
        $template->versions()->firstOrFail()->forceFill(['risk_level' => ProcedureRiskLevel::Critical])->save();
        $legacy = $this->startRun($template, $lead);
        $execution->execute($this->stepOf($legacy, 'a'), $lead, ProcedureStepRunStatus::Done);
        $this->assertFalse($execution->lacksCriticalSecondPerson($legacy->fresh()));
        $this->assertSame(ProcedureRunStatus::Completed, $execution->completeRun($legacy->fresh(), $lead)->status);
    }
}
