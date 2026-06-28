<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunExecutionHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureRunStatus, ProcedureStepRunStatus, ProcedureStepType};
use App\Models\{DiaryEntry, Organization, ProcedureRun, ProcedureTemplate, User};
use App\Services\Procedure\{ProcedureExecutionService, ProcedureTemplateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile Ausführungs-UI eines Prozedurlaufs (MVP-063): Anzeige, Schritt-
 * ausführung mit Reihenfolge-Sperre, Lauf-Abschluss und Abbruch über die
 * HTTP-Endpunkte.
 */
class ProcedureRunExecutionHttpTest extends TestCase {
    use RefreshDatabase;

    public function test_show_renders_steps_of_active_run(): void {
        [$user, $run] = $this->startedRun(['Erster Schritt', 'Zweiter Schritt']);

        $this->actingAs($user)
            ->get(route('procedure-runs.show', $run))
            ->assertOk()
            ->assertSee('Erster Schritt')
            ->assertSee('Zweiter Schritt')
            ->assertSee(__('procedure.run.complete'));
    }

    public function test_executing_first_step_marks_it_done(): void {
        [$user, $run] = $this->startedRun(['Erster Schritt', 'Zweiter Schritt']);
        $first = $run->stepRuns()->orderBy('id')->first();

        $this->actingAs($user)
            ->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.steps.execute', [$run, $first]), [
                'status' => 'done',
                'note' => 'erledigt',
            ])
            ->assertRedirect(route('procedure-runs.show', $run));

        $this->assertSame(ProcedureStepRunStatus::Done, $first->refresh()->status);
        $this->assertSame('erledigt', $first->note);
    }

    public function test_executing_blocked_step_is_rejected_and_stays_pending(): void {
        [$user, $run] = $this->startedRun(['Erster Schritt', 'Zweiter Schritt']);
        $second = $run->stepRuns()->orderBy('id')->get()->last();

        $this->actingAs($user)
            ->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.steps.execute', [$run, $second]), [
                'status' => 'done',
            ])
            ->assertRedirect(route('procedure-runs.show', $run))
            ->assertSessionHas('error');

        $this->assertSame(ProcedureStepRunStatus::Pending, $second->refresh()->status);
    }

    public function test_complete_run_after_all_required_steps_done(): void {
        [$user, $run] = $this->startedRun(['Nur ein Schritt']);
        $only = $run->stepRuns()->firstOrFail();
        app(ProcedureExecutionService::class)->execute($only, $user, ProcedureStepRunStatus::Done);

        $this->actingAs($user)
            ->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.complete', $run))
            ->assertRedirect();

        $this->assertSame(ProcedureRunStatus::Completed, $run->refresh()->status);
    }

    public function test_complete_run_blocked_when_required_step_open(): void {
        [$user, $run] = $this->startedRun(['Offener Pflichtschritt']);

        $this->actingAs($user)
            ->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.complete', $run))
            ->assertRedirect(route('procedure-runs.show', $run))
            ->assertSessionHas('error');

        $this->assertSame(ProcedureRunStatus::Open, $run->refresh()->status);
    }

    public function test_abort_run_sets_aborted_status(): void {
        [$user, $run] = $this->startedRun(['Schritt']);

        $this->actingAs($user)
            ->post(route('procedure-runs.abort', $run), ['reason' => 'Abbruch im Test'])
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(ProcedureRunStatus::Aborted, $run->status);
        $this->assertSame('Abbruch im Test', $run->abort_reason);
    }

    public function test_foreign_organization_run_is_not_accessible(): void {
        [, $run] = $this->startedRun(['Schritt']);
        $stranger = User::factory()->teamleitung()->create();

        $this->actingAs($stranger)
            ->get(route('procedure-runs.show', $run))
            ->assertNotFound();
    }

    public function test_wait_step_early_continue_requires_reason_and_records_deviation(): void {
        // MVP-063/064: mobiler Warteschritt — Start blockiert, vorzeitige
        // Fortsetzung nur mit Pflichtbegründung als auditierte Abweichung.
        [$user, $run] = $this->startedRunWithWait();
        $step = $run->stepRuns()->orderBy('id')->first();

        $this->actingAs($user)
            ->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.steps.wait.begin', [$run, $step]))
            ->assertRedirect(route('procedure-runs.show', $run));
        $this->assertSame(ProcedureStepRunStatus::Blocked, $step->refresh()->status);

        // Ohne Begründung → Validierungsfehler, Schritt bleibt blockiert.
        $this->actingAs($user)
            ->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.steps.wait.continue', [$run, $step]), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(ProcedureStepRunStatus::Blocked, $step->refresh()->status);

        // Mit Begründung → auditierte Abweichung.
        $this->actingAs($user)
            ->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.steps.wait.continue', [$run, $step]), ['reason' => 'Dringend benötigt'])
            ->assertRedirect();
        $this->assertSame(ProcedureStepRunStatus::Deviated, $step->refresh()->status);
    }

    /**
     * Startet einen echten Lauf über den Service und gibt [User, Run] zurück.
     *
     * @param  list<string>  $stepLabels
     * @return array{0: User, 1: ProcedureRun}
     */
    private function startedRun(array $stepLabels): array {
        $user = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $user->organization);

        $template = $this->publishedTemplate($user->organization, $user, $stepLabels);
        $diary = DiaryEntry::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
        ]);
        $run = app(ProcedureExecutionService::class)->start($template, $diary, $user);

        return [$user, $run->fresh(['stepRuns.stepDef'])];
    }

    /**
     * @param  list<string>  $stepLabels
     */
    private function publishedTemplate(Organization $org, User $user, array $stepLabels): ProcedureTemplate {
        $templates = app(ProcedureTemplateService::class);
        $template = $templates->create($org, $user, [
            'code' => 'RUN-' . uniqid(),
            'name' => 'Ausführungs-Test',
        ]);
        $version = $template->versions()->firstOrFail();
        foreach ($stepLabels as $i => $label) {
            $templates->addStepDef($version, [
                'code' => 'step' . $i,
                'step_type' => ProcedureStepType::Confirm->value,
                'label' => $label,
            ]);
        }
        $templates->publish($version, $user);

        return $template->fresh(['versions.steps']);
    }

    /**
     * Startet einen Lauf mit einem einzelnen serverseitigen Warteschritt.
     *
     * @return array{0: User, 1: ProcedureRun}
     */
    private function startedRunWithWait(): array {
        $user = User::factory()->teamleitung()->create();
        app()->instance('currentOrganization', $user->organization);

        $templates = app(ProcedureTemplateService::class);
        $template = $templates->create($user->organization, $user, [
            'code' => 'WAIT-' . uniqid(),
            'name' => 'Warte-Test',
        ]);
        $version = $template->versions()->firstOrFail();
        $templates->addStepDef($version, [
            'code' => 'wait0',
            'step_type' => ProcedureStepType::Wait->value,
            'label' => 'Trockenzeit',
            'config' => ['wait_seconds' => 3600],
        ]);
        $templates->publish($version, $user);

        $diary = DiaryEntry::factory()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
        ]);
        $run = app(ProcedureExecutionService::class)->start($template->fresh(['versions.steps']), $diary, $user);

        return [$user, $run->fresh(['stepRuns.stepDef'])];
    }
}
