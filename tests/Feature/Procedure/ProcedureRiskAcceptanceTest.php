<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRiskAcceptanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureDeviationProposedAction, ProcedureDeviationSeverity, ProcedureDeviationType, ProcedureRunEventType, ProcedureRunStatus};
use App\Enums\User\Permission;
use App\Exceptions\ProcedureDeviationValidationException;
use App\Models\Procedure\ProcedureDeviation;
use App\Services\Procedure\{DeviationRecorder, ProcedureExecutionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Procedure\Concerns\BuildsProcedureRuns;
use Tests\TestCase;

/** Risikoakzeptanz einer kritischen Abweichung über die Lauf-Ansicht (MVP-1567). */
class ProcedureRiskAcceptanceTest extends TestCase {
    use BuildsProcedureRuns;
    use RefreshDatabase;

    public function test_critical_deviation_blocks_until_the_risk_is_accepted_with_a_reason(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [['code' => 'check']]), $lead);
        $step = $this->stepOf($run, 'check');

        $this->actingAs($lead)->post(route('procedure-runs.steps.deviation', [$run, $step]), [
            'deviation_type' => ProcedureDeviationType::SafetyBlock->value,
            'proposed_action' => ProcedureDeviationProposedAction::Escalate->value,
            'reason_text' => 'Schutzleiter am Verteiler nicht messbar, Anlage bleibt aus.',
        ])->assertSessionHas('success');
        $deviation = ProcedureDeviation::query()->firstOrFail();
        $this->assertSame(ProcedureDeviationSeverity::Critical, $deviation->severity);

        $this->actingAs($lead)->get(route('procedure-runs.show', $run))->assertOk()
            ->assertSee(__('procedure.deviation.risk_open'))
            ->assertSee(__('procedure.deviation.accept_risk'))
            ->assertSee(route('procedure-runs.steps.deviation.accept-risk', [$run, $step]));
        $this->actingAs($lead)->from(route('procedure-runs.show', $run))->post(route('procedure-runs.complete', $run))
            ->assertSessionHas('error', __('procedure.validation.criticalDeviationOpen'));

        // Ohne Recht keine Akzeptanz, ohne Begründung auch nicht.
        $worker = $this->colleagueOf($lead, 'user');
        $this->assertFalse($worker->can(Permission::ProcedureDeviationAcceptRisk->value));
        $this->actingAs($worker)->post(route('procedure-runs.steps.deviation.accept-risk', [$run, $step]), ['note' => 'in Ordnung so'])->assertForbidden();
        $this->actingAs($worker)->get(route('procedure-runs.show', $run))->assertOk()->assertSee(__('procedure.deviation.accept_risk_denied'));
        $this->actingAs($lead)->post(route('procedure-runs.steps.deviation.accept-risk', [$run, $step]), ['note' => ''])->assertSessionHasErrors('note');
        $this->assertNull($deviation->fresh()->risk_accepted_at);

        $this->actingAs($lead)->post(route('procedure-runs.steps.deviation.accept-risk', [$run, $step]), ['note' => 'Anlage bleibt bis zur Reparatur stromlos.'])
            ->assertSessionHas('success', __('procedure.flash.criticalRiskAccepted'));
        $deviation->refresh();
        $this->assertSame($lead->id, $deviation->risk_accepted_by_user_id);
        $event = $run->journal()->reorder()->where('event_type', ProcedureRunEventType::CriticalRiskAccepted->value)->firstOrFail();
        $this->assertSame('Anlage bleibt bis zur Reparatur stromlos.', data_get($event->payload, 'note'));
        $this->assertSame($lead->id, $event->actor_user_id);

        // Der Bericht „Prozedur-Abweichungen“ bekommt damit seinen Filter „Risiko akzeptiert“.
        $this->actingAs($lead)->get(route('reports.procedure-deviations', ['risk' => 'yes']))->assertOk()->assertSee($step->stepDef->label);

        $this->actingAs($lead)->post(route('procedure-runs.complete', $run))->assertSessionHas('success');
        $this->assertSame(ProcedureRunStatus::Completed, $run->fresh()->status);
    }

    public function test_follow_up_item_instead_of_acceptance_lets_the_run_complete(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [['code' => 'check']]), $lead);

        $deviation = app(DeviationRecorder::class)->record($this->stepOf($run, 'check'), $lead, [
            'deviation_type' => ProcedureDeviationType::SafetyBlock->value,
            'proposed_action' => ProcedureDeviationProposedAction::OpenIssue->value,
            'reason_text' => 'Absturzsicherung fehlt, Nacharbeit als offener Punkt.',
        ]);

        $this->assertNotNull($deviation->open_issue_id);
        $this->assertSame([], app(DeviationRecorder::class)->blockingDeviationIdsFor($run));
        $this->assertSame(ProcedureRunStatus::Completed, app(ProcedureExecutionService::class)->completeRun($run->fresh(), $lead)->status);
    }

    public function test_acceptance_is_limited_to_open_critical_deviations(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [['code' => 'a', 'blocking' => false], ['code' => 'b', 'blocking' => false]]), $lead);
        $recorder = app(DeviationRecorder::class);
        $medium = $recorder->record($this->stepOf($run, 'a'), $lead, [
            'deviation_type' => ProcedureDeviationType::Partial->value,
            'reason_text' => 'Nur zwei von drei Messpunkten erreichbar.',
        ]);
        $critical = $recorder->record($this->stepOf($run, 'b'), $lead, [
            'deviation_type' => ProcedureDeviationType::SafetyBlock->value,
            'reason_text' => 'Arbeiten wegen Gasgeruch abgebrochen.',
        ]);

        foreach ([
            [$medium, 'Begründung liegt vor', ProcedureDeviationValidationException::REASON_RISK_NOT_CRITICAL],
            [$critical, '  ', ProcedureDeviationValidationException::REASON_RISK_NOTE_MISSING],
        ] as [$deviation, $note, $reason]) {
            try {
                $recorder->acceptRisk($deviation, $lead, $note);
                $this->fail('Expected ProcedureDeviationValidationException');
            } catch (ProcedureDeviationValidationException $e) {
                $this->assertSame($reason, $e->reason);
            }
        }

        $recorder->acceptRisk($critical, $lead, 'Versorger hat freigegeben.');
        try {
            $recorder->acceptRisk($critical->fresh(), $lead, 'noch einmal');
            $this->fail('Expected ProcedureDeviationValidationException');
        } catch (ProcedureDeviationValidationException $e) {
            $this->assertSame(ProcedureDeviationValidationException::REASON_RISK_ALREADY_ACCEPTED, $e->reason);
        }
    }
}
