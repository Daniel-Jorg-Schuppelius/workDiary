<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeviationRecorderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\ProcedureDeviationProposedAction;
use App\Enums\Procedure\ProcedureDeviationSeverity;
use App\Enums\Procedure\ProcedureDeviationType;
use App\Enums\Procedure\ProcedureRunEventType;
use App\Enums\Procedure\ProcedureStepRunStatus;
use App\Enums\Procedure\ProcedureStepType;
use App\Exceptions\ProcedureDeviationValidationException;
use App\Models\DiaryEntry;
use App\Models\OpenIssue;
use App\Models\Organization;
use App\Models\ProcedureDeviation;
use App\Models\ProcedureRun;
use App\Models\ProcedureStepRun;
use App\Models\User;
use App\Services\Procedure\DeviationRecorder;
use App\Services\Procedure\ProcedureExecutionService;
use App\Services\Procedure\ProcedureTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviationRecorderTest extends TestCase {
    use RefreshDatabase;

    private ProcedureTemplateService $templates;

    private ProcedureExecutionService $executor;

    private DeviationRecorder $deviations;

    protected function setUp(): void {
        parent::setUp();
        $this->templates = app(ProcedureTemplateService::class);
        $this->executor = app(ProcedureExecutionService::class);
        $this->deviations = app(DeviationRecorder::class);
    }

    public function test_record_sets_step_deviated_and_logs_event(): void {
        [, $user, $stepRun, $run] = $this->makeRun();

        $deviation = $this->deviations->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::NotPossible->value,
            'reason_text' => 'Backup nicht moeglich, Quelle unerreichbar.',
        ]);

        $this->assertInstanceOf(ProcedureDeviation::class, $deviation);
        $this->assertSame(ProcedureDeviationSeverity::High, $deviation->severity);
        $this->assertSame(ProcedureStepRunStatus::Deviated, $stepRun->fresh()->status);
        $this->assertSame((int) $deviation->id, (int) $stepRun->fresh()->deviation_id);
        $this->assertSame(
            1,
            $run->events()->where('event_type', ProcedureRunEventType::DeviationRecorded->value)->count(),
        );
    }

    public function test_record_rejects_when_reason_text_is_too_short(): void {
        [, $user, $stepRun] = $this->makeRun();

        try {
            $this->deviations->record($stepRun, $user, [
                'deviation_type' => ProcedureDeviationType::Partial->value,
                'reason_text' => 'zu kurz',
            ]);
            $this->fail('Expected ProcedureDeviationValidationException');
        } catch (ProcedureDeviationValidationException $e) {
            $this->assertSame(ProcedureDeviationValidationException::CODE_REASON_TOO_SHORT, $e->errorCode);
        }
    }

    public function test_open_issue_action_creates_linked_issue(): void {
        [, $user, $stepRun, $run] = $this->makeRun();

        $deviation = $this->deviations->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::FailedCheck->value,
            'reason_text' => 'Pruefwert erheblich ausserhalb der Toleranz, Folgepruefung noetig.',
            'proposed_action' => ProcedureDeviationProposedAction::OpenIssue->value,
            'issue_title' => 'Folgepruefung Sensor X',
        ]);

        $this->assertNotNull($deviation->open_issue_id);
        $issue = OpenIssue::query()->findOrFail($deviation->open_issue_id);
        $this->assertSame((int) $deviation->id, (int) $issue->source_ref_id);
        $this->assertSame(
            1,
            $run->events()->where('event_type', ProcedureRunEventType::DeviationActionTriggered->value)->count(),
        );
    }

    public function test_critical_deviation_blocks_run_complete_without_risk_accept(): void {
        [$org, $user, $stepRun, $run] = $this->makeRun();

        $this->deviations->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::SafetyBlock->value,
            'reason_text' => 'Sicherheitsabbruch wegen unmittelbarer Gefahr fuer Personal.',
            'proposed_action' => ProcedureDeviationProposedAction::Escalate->value,
        ]);

        try {
            $this->executor->completeRun($run->refresh(), $user);
            $this->fail('Expected ProcedureDeviationValidationException');
        } catch (ProcedureDeviationValidationException $e) {
            $this->assertSame(ProcedureDeviationValidationException::CODE_CRITICAL_OPEN, $e->errorCode);
        }
    }

    public function test_accept_risk_unblocks_run_complete_and_logs_event(): void {
        [, $user, $stepRun, $run] = $this->makeRun();

        $deviation = $this->deviations->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::SafetyBlock->value,
            'reason_text' => 'Sicherheitsabbruch dokumentiert, Risiko bekannt und tragbar.',
            'proposed_action' => ProcedureDeviationProposedAction::Escalate->value,
        ]);

        $this->deviations->acceptRisk($deviation, $user, 'Akzeptiert nach Ruecksprache.');

        $completed = $this->executor->completeRun($run->refresh(), $user);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(
            1,
            $run->events()->where('event_type', ProcedureRunEventType::CriticalRiskAccepted->value)->count(),
        );
    }

    public function test_default_severity_is_derived_from_deviation_type(): void {
        [, $user, $stepRun] = $this->makeRun();

        $deviation = $this->deviations->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::AlternativeMethod->value,
            'reason_text' => 'Alternative Reinigungsmethode angewendet, Ergebnis identisch.',
        ]);

        $this->assertSame(ProcedureDeviationSeverity::Low, $deviation->severity);
    }

    public function test_duplicate_recording_is_rejected(): void {
        [, $user, $stepRun] = $this->makeRun();

        $this->deviations->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::Partial->value,
            'reason_text' => 'Teilweise erfuellt, Restmaterial fehlt fuer Abschluss.',
        ]);

        $this->expectException(ProcedureDeviationValidationException::class);
        $this->deviations->record($stepRun->refresh(), $user, [
            'deviation_type' => ProcedureDeviationType::Partial->value,
            'reason_text' => 'Erneut versucht und erneut nur teilweise erfuellt.',
        ]);
    }

    /** @return array{0: Organization, 1: User, 2: ProcedureStepRun, 3: ProcedureRun} */
    private function makeRun(): array {
        $user = User::factory()->geschaeftsfuehrung()->create();
        $org = $user->organization;
        $template = $this->templates->create($org, $user, [
            'code' => 'DV-'.uniqid(),
            'name' => 'Abweichungs-Vorlage',
        ]);
        $version = $template->versions->first();
        $this->templates->addStepDef($version, [
            'code' => 'inspect',
            'step_type' => ProcedureStepType::Confirm->value,
            'label' => 'Pruefen',
            'required' => true,
        ]);
        $this->templates->publish($version, $user);

        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template->fresh(['versions.steps']), $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();

        return [$org, $user, $stepRun, $run];
    }
}
