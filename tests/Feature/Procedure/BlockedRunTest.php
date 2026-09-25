<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BlockedRunTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureDeviationSeverity, ProcedureDeviationType, ProcedureRunEventType, ProcedureRunStatus, ProcedureStepRunStatus, ProcedureStepType};
use App\Exceptions\ProcedureStepBlockedException;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureDeviation, ProcedureRun, ProcedureStepRun};
use App\Services\Procedure\{BlockedRunsReport, DeviationRecorder, ProcedureExecutionService, ProcedureRunBlockTracker, ProcedureTemplateService, SecondPersonGate, WaitStepService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** MVP-897: Laufstatus „Blocked“ mit Sperrgrund und Bericht. */
final class BlockedRunTest extends TestCase {
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->user = User::factory()->geschaeftsfuehrung()->create();
        app()->instance('currentOrganization', $this->user->organization);
    }

    /** @param list<array<string, mixed>> $steps */
    private function startRun(array $steps): ProcedureRun {
        $templates = app(ProcedureTemplateService::class);
        $template = $templates->create($this->user->organization, $this->user, ['code' => 'B-' . uniqid(), 'name' => 'Estrich trocknen']);
        $version = $template->versions->first();
        foreach ($steps as $step) {
            $templates->addStepDef($version, $step);
        }
        $templates->publish($version, $this->user);

        return app(ProcedureExecutionService::class)->start($template->fresh(), DiaryEntry::factory()->for($this->user)->create(), $this->user);
    }

    private function step(ProcedureRun $run, string $code): ProcedureStepRun {
        return $run->stepRuns()->whereHas('stepDef', fn ($q) => $q->where('code', $code))->firstOrFail();
    }

    public function test_wait_blocks_the_run_and_elapsing_releases_it_with_duration(): void {
        Carbon::setTestNow('2026-09-25 08:00:00');
        $run = $this->startRun([
            ['code' => 'MIX', 'step_type' => ProcedureStepType::Confirm->value, 'label' => 'Anmischen'],
            ['code' => 'DRY', 'step_type' => ProcedureStepType::Confirm->value, 'label' => 'Trocknen'],
        ]);
        app(ProcedureExecutionService::class)->execute($this->step($run, 'MIX'), $this->user, ProcedureStepRunStatus::Done);
        app(WaitStepService::class)->beginWait($this->step($run, 'DRY'), 7200);

        $run->refresh();
        $this->assertSame(ProcedureRunStatus::Blocked, $run->status);
        $this->assertSame(ProcedureStepBlockedException::REASON_WAIT_NOT_ELAPSED, $run->blocked_reason);

        Carbon::setTestNow('2026-09-25 13:00:00');
        $report = app(BlockedRunsReport::class);
        $this->assertSame([], $report->current(), 'abgelaufene Wartezeit gibt den Lauf beim Lesen frei');
        $run->refresh();
        $this->assertSame(ProcedureRunStatus::InProgress, $run->status);
        $this->assertNull($run->blocked_at);

        $unblocked = $run->journal()->reorder()->where('event_type', ProcedureRunEventType::RunUnblocked->value)->firstOrFail();
        $this->assertSame(7200, $unblocked->payload['seconds'], 'Dauer endet mit der Wartezeit, nicht mit dem Lesen');
        $periods = $report->periods(Carbon::parse('2026-09-01')->toImmutable(), Carbon::parse('2026-09-30')->toImmutable());
        $this->assertSame([['reason' => 'waitNotElapsed', 'template' => 'Estrich trocknen', 'count' => 1, 'avg_hours' => 2.0, 'max_hours' => 2.0]], $periods);
    }

    public function test_requested_second_person_and_critical_deviation_block_until_resolved(): void {
        $second = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->user->organization_id]);
        $run = $this->startRun([
            ['code' => 'CHECK', 'step_type' => ProcedureStepType::Confirm->value, 'label' => 'Prüfen', 'requires_second_person' => true, 'config' => ['second_person_self_exclusion' => true]],
            ['code' => 'NEXT', 'step_type' => ProcedureStepType::Confirm->value, 'label' => 'Weiter'],
        ]);
        $this->assertSame(ProcedureRunStatus::Open, $run->fresh()->status, 'Vier-Augen-Schritt allein sperrt nicht');

        $gate = app(SecondPersonGate::class);
        $check = $this->step($run, 'CHECK');
        $gate->request($check, $this->user);
        $this->assertSame(ProcedureStepBlockedException::REASON_SECOND_PERSON_REQUIRED, $run->fresh()->blocked_reason);

        $gate->take($check->fresh(), $second);
        $gate->sign($check->fresh(), $second);
        $this->assertSame(ProcedureRunStatus::Open, $run->fresh()->status);

        app(DeviationRecorder::class)->record($this->step($run, 'NEXT'), $this->user, [
            'deviation_type' => ProcedureDeviationType::cases()[0]->value,
            'severity' => ProcedureDeviationSeverity::Critical->value,
            'reason_text' => 'Messwert außerhalb der Toleranz',
        ]);
        $this->assertSame(ProcedureRunBlockTracker::REASON_CRITICAL_DEVIATION, $run->fresh()->blocked_reason);

        $this->actingAs($this->user)->get(route('reports.procedure-blocked'))->assertOk()->assertSee(__('procedure.blocked.criticalDeviationOpen'));
        $this->actingAs($this->user)->get(route('procedure-runs.show', $run))->assertOk()->assertSee(__('procedure.blocked.criticalDeviationOpen'));

        app(DeviationRecorder::class)->acceptRisk(ProcedureDeviation::query()->firstOrFail(), $this->user, 'freigegeben');
        $this->assertSame(ProcedureRunStatus::InProgress, $run->fresh()->status);
        $this->assertSame(2, $run->journal()->reorder()->where('event_type', ProcedureRunEventType::RunUnblocked->value)->count());
    }
}
