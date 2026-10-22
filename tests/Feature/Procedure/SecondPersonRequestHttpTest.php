<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecondPersonRequestHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureRunEventType, ProcedureStepRunStatus, ProcedureStepType};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureRun, ProcedureStepRun};
use App\Services\Procedure\{ProcedureExecutionService, ProcedureTemplateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vier-Augen-Schritt über die Oberfläche (MVP-1301): anfordern, gegenzeichnen,
 * widerrufen. Vorher war der Gegenzeichnen-Knopf unerreichbar, weil der Schritt
 * ohne Gegenzeichnung immer als gesperrt galt.
 */
class SecondPersonRequestHttpTest extends TestCase {
    use RefreshDatabase;

    private User $executor;

    private User $colleague;

    private ProcedureRun $run;

    private ProcedureStepRun $step;

    protected function setUp(): void {
        parent::setUp();
        $this->executor = User::factory()->user()->create();
        $this->colleague = User::factory()->teamleitung()->create(['organization_id' => $this->executor->organization_id]);
        app()->instance('currentOrganization', $this->executor->organization);

        $templates = app(ProcedureTemplateService::class);
        $template = $templates->create($this->executor->organization, $this->colleague, ['code' => '4A-' . uniqid(), 'name' => 'Vier-Augen-Test']);
        $version = $template->versions()->firstOrFail();
        $templates->addStepDef($version, [
            'code' => 'critical',
            'step_type' => ProcedureStepType::Confirm->value,
            'label' => 'Anlage freischalten',
            'requires_second_person' => true,
            'config' => ['second_person_self_exclusion' => true],
        ]);
        $templates->publish($version, $this->colleague);

        $diary = DiaryEntry::factory()->create(['organization_id' => $this->executor->organization_id, 'user_id' => $this->executor->id]);
        $this->run = app(ProcedureExecutionService::class)->start($template->fresh(['versions.steps']), $diary, $this->executor);
        $this->step = $this->run->stepRuns()->firstOrFail();
    }

    public function test_four_eyes_bar_offers_request_to_the_executor(): void {
        $this->actingAs($this->executor)
            ->get(route('procedure-runs.show', $this->run))
            ->assertOk()
            ->assertSee('data-second-person', false)
            ->assertSee(route('procedure-runs.steps.second-person.request', [$this->run, $this->step]), false)
            ->assertDontSee(__('procedure.blocked.secondPersonRequired'));
    }

    public function test_request_marks_the_step_journals_and_notifies_the_lead_once(): void {
        $show = route('procedure-runs.show', $this->run);

        $this->actingAs($this->executor)->from($show)
            ->post(route('procedure-runs.steps.second-person.request', [$this->run, $this->step]))
            ->assertRedirect($show);
        $this->actingAs($this->executor)->from($show)
            ->post(route('procedure-runs.steps.second-person.request', [$this->run, $this->step]));

        $step = $this->step->fresh();
        $this->assertNotNull($step->second_person_requested_at);
        $this->assertSame($this->executor->id, $step->second_person_requester_user_id);
        $this->assertSame(1, $this->run->journal()->where('event_type', ProcedureRunEventType::SecondPersonRequested->value)->count());
        $notes = $this->colleague->notifications()->get()->filter(fn ($n): bool => ($n->data['event'] ?? null) === 'procedure.secondPersonRequested');
        $this->assertCount(1, $notes);
    }

    public function test_colleague_countersigns_but_the_requester_may_not(): void {
        $show = route('procedure-runs.show', $this->run);
        $this->actingAs($this->executor)->post(route('procedure-runs.steps.second-person.request', [$this->run, $this->step]));

        $this->actingAs($this->executor)->get($show)
            ->assertDontSee(route('procedure-runs.steps.second-person', [$this->run, $this->step]), false);
        $this->actingAs($this->executor)->from($show)
            ->post(route('procedure-runs.steps.second-person', [$this->run, $this->step]))
            ->assertSessionHas('error', __('procedure.secondPerson.reason.selfNotAllowed'));
        $this->assertNull($this->step->fresh()->second_person_signed_at);

        $this->actingAs($this->colleague)->get($show)
            ->assertSee(route('procedure-runs.steps.second-person', [$this->run, $this->step]), false);
        $this->actingAs($this->colleague)->from($show)
            ->post(route('procedure-runs.steps.second-person', [$this->run, $this->step]))
            ->assertSessionHas('success');

        $this->assertSame($this->colleague->id, $this->step->fresh()->second_person_user_id);
        $this->actingAs($this->executor)->get($show)
            ->assertSee(route('procedure-runs.steps.execute', [$this->run, $this->step]), false);
    }

    public function test_revoke_needs_permission_and_reason_and_reopens_the_request(): void {
        $show = route('procedure-runs.show', $this->run);
        $this->actingAs($this->executor)->post(route('procedure-runs.steps.second-person.request', [$this->run, $this->step]));
        $this->actingAs($this->colleague)->post(route('procedure-runs.steps.second-person', [$this->run, $this->step]));
        $this->actingAs($this->executor)->post(route('procedure-runs.steps.execute', [$this->run, $this->step]), ['status' => 'done']);
        $this->assertSame(ProcedureStepRunStatus::Done, $this->step->fresh()->status);

        $this->actingAs($this->executor)
            ->post(route('procedure-runs.steps.second-person.revoke', [$this->run, $this->step]), ['reason' => 'Falsche Anlage geprüft'])
            ->assertForbidden();
        $this->actingAs($this->colleague)->from($show)
            ->post(route('procedure-runs.steps.second-person.revoke', [$this->run, $this->step]), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->colleague)->from($show)
            ->post(route('procedure-runs.steps.second-person.revoke', [$this->run, $this->step]), ['reason' => 'Falsche Anlage geprüft'])
            ->assertRedirect($show);

        $step = $this->step->fresh();
        $this->assertSame(ProcedureStepRunStatus::Pending, $step->status);
        $this->assertNull($step->second_person_user_id);
        $this->assertNull($step->second_person_signed_at);
        $this->assertNotNull($step->second_person_requested_at);
        $event = $this->run->journal()->where('event_type', ProcedureRunEventType::SecondPersonRevoked->value)->sole();
        $this->assertSame('Falsche Anlage geprüft', $event->payload['reason'] ?? null);
    }

    public function test_request_needs_the_request_permission(): void {
        $accounting = User::factory()->buchhaltung()->create(['organization_id' => $this->executor->organization_id]);

        $this->actingAs($accounting)
            ->post(route('procedure-runs.steps.second-person.request', [$this->run, $this->step]))
            ->assertForbidden();
    }
}
