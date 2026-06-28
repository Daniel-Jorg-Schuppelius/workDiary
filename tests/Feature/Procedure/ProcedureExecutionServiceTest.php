<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureExecutionServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureRunEventType, ProcedureRunStatus, ProcedureStepRunStatus, ProcedureStepType};
use App\Exceptions\{ProcedureRunIncompleteException, ProcedureStepBlockedException};
use App\Models\{DiaryEntry, Organization, ProcedureRun, ProcedureTemplate, User};
use App\Services\Procedure\{ProcedureExecutionService, ProcedureTemplateService, WaitStepService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedureExecutionServiceTest extends TestCase {
    use RefreshDatabase;

    private ProcedureTemplateService $templates;
    private ProcedureExecutionService $executor;

    protected function setUp(): void {
        parent::setUp();
        $this->templates = app(ProcedureTemplateService::class);
        $this->executor = app(ProcedureExecutionService::class);
    }

    public function test_start_creates_step_runs_in_pending(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makePublishedTemplate($org, $user, ['A', 'B', 'C']);

        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);

        $this->assertInstanceOf(ProcedureRun::class, $run);
        $this->assertSame(ProcedureRunStatus::Open, $run->status);
        $this->assertCount(3, $run->stepRuns);
        foreach ($run->stepRuns as $stepRun) {
            $this->assertSame(ProcedureStepRunStatus::Pending, $stepRun->status);
        }
        $this->assertSame(1, $run->events()->where('event_type', ProcedureRunEventType::RunStarted->value)->count());
    }

    public function test_can_execute_blocks_when_previous_blocking_step_pending(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makePublishedTemplate($org, $user, ['A', 'B']);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $second = $run->stepRuns->last();

        try {
            $this->executor->canExecute($second, $user);
            $this->fail('Expected ProcedureStepBlockedException');
        } catch (ProcedureStepBlockedException $e) {
            $this->assertSame(ProcedureStepBlockedException::REASON_PREVIOUS_STEP_INCOMPLETE, $e->reason);
        }
    }

    public function test_execute_sets_status_done_and_logs_event(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makePublishedTemplate($org, $user, ['A']);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $stepRun = $run->stepRuns->first();

        $result = $this->executor->execute($stepRun, $user, ProcedureStepRunStatus::Done, [
            'note' => 'ok',
        ]);

        $this->assertSame(ProcedureStepRunStatus::Done, $result->status);
        $this->assertSame($user->id, $result->executed_by_user_id);
        $this->assertNotNull($result->executed_at);
        $this->assertSame('ok', $result->note);

        $run->refresh();
        $this->assertSame(ProcedureRunStatus::InProgress, $run->status);
        $this->assertSame(
            1,
            $run->events()->where('event_type', ProcedureRunEventType::StepCompleted->value)->count(),
        );
    }

    public function test_execute_is_blocked_while_wait_not_elapsed(): void {
        // MVP-064: ein laufender Warteschritt darf auch über den allgemeinen
        // Ausführungspfad nicht vorzeitig abgeschlossen werden (kein Umgehen
        // der serverseitigen Frist durch Reload/anderen Client).
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makePublishedTemplate($org, $user, ['A']);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $stepRun = $run->stepRuns->first();

        app(WaitStepService::class)->beginWait($stepRun, 3600);

        $this->assertSame(
            ProcedureStepBlockedException::REASON_WAIT_NOT_ELAPSED,
            $this->executor->blockReasonFor($stepRun->fresh(), $user),
        );

        try {
            $this->executor->execute($stepRun->fresh(), $user, ProcedureStepRunStatus::Done);
            $this->fail('Warteschritt darf nicht vorzeitig ausführbar sein.');
        } catch (ProcedureStepBlockedException $e) {
            $this->assertSame(ProcedureStepBlockedException::REASON_WAIT_NOT_ELAPSED, $e->reason);
        }
    }

    public function test_execute_unlocks_subsequent_step(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makePublishedTemplate($org, $user, ['A', 'B']);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $first = $run->stepRuns->first();
        $second = $run->stepRuns->last();

        $this->executor->execute($first, $user, ProcedureStepRunStatus::Done);

        $this->assertTrue($this->executor->canExecute($second->fresh(), $user));
    }

    public function test_complete_blocks_when_required_step_pending(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makePublishedTemplate($org, $user, ['A', 'B']);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);

        try {
            $this->executor->completeRun($run, $user);
            $this->fail('Expected ProcedureRunIncompleteException');
        } catch (ProcedureRunIncompleteException $e) {
            $this->assertCount(2, $e->missingStepRunIds);
        }

        $this->assertSame(
            1,
            $run->events()->where('event_type', ProcedureRunEventType::RunCompletionRejected->value)->count(),
        );
    }

    public function test_complete_succeeds_when_all_required_finalized(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makePublishedTemplate($org, $user, ['A', 'B']);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);

        foreach ($run->stepRuns as $stepRun) {
            $this->executor->execute($stepRun->fresh(), $user, ProcedureStepRunStatus::Done);
        }

        $completed = $this->executor->completeRun($run->fresh(), $user);

        $this->assertSame(ProcedureRunStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(
            1,
            $completed->events()->where('event_type', ProcedureRunEventType::RunCompleted->value)->count(),
        );
    }

    public function test_abort_sets_status_and_logs_event(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makePublishedTemplate($org, $user, ['A']);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);

        $aborted = $this->executor->abort($run, $user, 'Hardware kaputt');

        $this->assertSame(ProcedureRunStatus::Aborted, $aborted->status);
        $this->assertSame('Hardware kaputt', $aborted->abort_reason);
        $this->assertNotNull($aborted->aborted_at);
        $this->assertSame(
            1,
            $aborted->events()->where('event_type', ProcedureRunEventType::RunAborted->value)->count(),
        );
    }

    /** @param  list<string>  $stepCodes */
    private function makePublishedTemplate(Organization $org, User $user, array $stepCodes): ProcedureTemplate {
        $template = $this->templates->create($org, $user, [
            'code' => 'EXEC-' . uniqid(),
            'name' => 'Exec Test',
        ]);
        $version = $template->versions->first();
        foreach ($stepCodes as $code) {
            $this->templates->addStepDef($version, [
                'code' => strtolower($code),
                'step_type' => ProcedureStepType::Confirm->value,
                'label' => 'Step ' . $code,
            ]);
        }
        $this->templates->publish($version, $user);

        return $template->fresh(['versions.steps']);
    }

    /** @return array{0: Organization, 1: User} */
    private function makeOrgAndUser(): array {
        $user = User::factory()->geschaeftsfuehrung()->create();
        return [$user->organization, $user];
    }
}
