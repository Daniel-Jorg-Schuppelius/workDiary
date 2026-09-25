<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FollowUpOrderAutomationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Enums\Diary\Status;
use App\Enums\Procedure\{ProcedureDeviationProposedAction, ProcedureDeviationType, ProcedureStepType};
use App\Models\Automation\{AutomationRule, AutomationRuleRun};
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry};
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureStepRun;
use App\Services\Diary\Automation\CreateFollowUpOrderAction;
use App\Services\OpenIssue\Automation\OpenIssueCreatedTrigger;
use App\Services\OpenIssue\OpenIssueService;
use App\Services\Procedure\Automation\DeviationRecordedTrigger;
use App\Services\Procedure\{DeviationRecorder, ProcedureExecutionService, ProcedureTemplateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-880/881: Folgeauftrag per Regel und aus der Prozedur-Abweichung. */
class FollowUpOrderAutomationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function rule(string $trigger, array $conditions): AutomationRule {
        return AutomationRule::create([
            'organization_id' => $this->organization->id,
            'name' => 'Folgeauftrag bei hoher Schwere',
            'trigger_event' => $trigger,
            'conditions' => $conditions,
            'actions' => [['type' => CreateFollowUpOrderAction::TYPE, 'params' => []]],
            'is_active' => true,
            'priority' => 10,
        ]);
    }

    public function test_rule_creates_follow_up_for_matching_open_issue_only(): void {
        $creator = $this->orgUser();
        $assignee = $this->orgUser();
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH', 'created_by' => $creator->id]);
        $entry = DiaryEntry::factory()->for($creator)->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $this->rule(OpenIssueCreatedTrigger::KEY, ['all' => [['field' => 'severity', 'op' => 'in', 'value' => ['high', 'critical']]]]);
        $this->actingAs($creator);

        $service = app(OpenIssueService::class);
        $low = $service->create($entry, $creator, ['title' => 'Kratzer am Rahmen', 'severity' => 'low']);
        $high = $service->create($entry, $creator, ['title' => 'Heizung ausgefallen', 'severity' => 'high', 'assignee_user_id' => $assignee->id]);

        $this->assertNull($low->fresh()->follow_up_diary_entry_id);
        $followUpId = $high->fresh()->follow_up_diary_entry_id;
        $this->assertNotNull($followUpId);
        $followUp = DiaryEntry::query()->findOrFail($followUpId);
        $this->assertSame($assignee->id, (int) $followUp->user_id);
        $this->assertSame($customer->id, (int) $followUp->customer_id);
        $this->assertSame('Heizung ausgefallen', $followUp->title);
        $this->assertSame(Status::Planned, $followUp->status);
        $this->assertSame(1, AutomationRuleRun::query()->where('decision', 'matched')->count());
    }

    public function test_admin_form_offers_catalog_and_rejects_mismatched_action(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin);

        $this->get(route('admin.automations.create'))
            ->assertOk()
            ->assertSee(__('automation.trigger.open_issue_created'))
            ->assertSee(__('automation.action.diary_follow_up'));

        $this->post(route('admin.automations.store'), [
            'name' => 'Falsch kombiniert',
            'trigger_event' => 'expense.submitted',
            'action_type' => CreateFollowUpOrderAction::TYPE,
            'conditions' => '{"all":[]}',
        ])->assertSessionHasErrors('action_type');

        $this->post(route('admin.automations.store'), [
            'name' => 'Unbekannter Auslöser',
            'trigger_event' => 'nothing.happened',
            'action_type' => CreateFollowUpOrderAction::TYPE,
            'conditions' => '{"all":[]}',
        ])->assertSessionHasErrors('trigger_event');

        $this->post(route('admin.automations.store'), [
            'name' => 'Folgeauftrag',
            'trigger_event' => OpenIssueCreatedTrigger::KEY,
            'action_type' => CreateFollowUpOrderAction::TYPE,
            'conditions' => '{"all":[]}',
        ])->assertRedirect(route('admin.automations.index'));

        $rule = AutomationRule::query()->where('name', 'Folgeauftrag')->firstOrFail();
        $this->assertSame([['type' => CreateFollowUpOrderAction::TYPE, 'params' => []]], $rule->actions);
    }

    public function test_deviation_with_new_diary_entry_creates_follow_up(): void {
        ['user' => $user, 'stepRun' => $stepRun] = $this->makeRun();

        $deviation = app(DeviationRecorder::class)->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::FailedCheck->value,
            'reason_text' => 'Dichtung porös, Austausch bei Folgetermin nötig.',
            'proposed_action' => ProcedureDeviationProposedAction::NewDiaryEntry->value,
        ]);

        $this->assertNotNull($deviation->follow_up_diary_entry_id);
        $followUp = DiaryEntry::query()->findOrFail($deviation->follow_up_diary_entry_id);
        $this->assertStringContainsString('Dichtung porös', $followUp->content);
        $this->assertStringContainsString('Prüfen', (string) $followUp->title);
    }

    public function test_escalated_deviation_notifies_admins(): void {
        ['user' => $user, 'stepRun' => $stepRun] = $this->makeRun();
        $admin = $this->orgAdmin();

        app(DeviationRecorder::class)->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::FailedCheck->value,
            'reason_text' => 'Messwert deutlich außerhalb der Toleranz.',
            'proposed_action' => ProcedureDeviationProposedAction::Escalate->value,
        ]);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame('procedure.deviationEscalated', (string) ($admin->notifications()->first()?->data['event'] ?? ''));
    }

    public function test_rule_on_deviation_creates_follow_up(): void {
        ['user' => $user, 'stepRun' => $stepRun] = $this->makeRun();
        $this->rule(DeviationRecordedTrigger::KEY, ['all' => [['field' => 'deviation_type', 'op' => '=', 'value' => ProcedureDeviationType::FailedCheck->value]]]);

        $deviation = app(DeviationRecorder::class)->record($stepRun, $user, [
            'deviation_type' => ProcedureDeviationType::FailedCheck->value,
            'reason_text' => 'Filter verstopft, Tausch beim nächsten Besuch.',
        ]);

        $this->assertNotNull($deviation->fresh()->follow_up_diary_entry_id);
    }

    /** @return array{user: User, stepRun: ProcedureStepRun} */
    private function makeRun(): array {
        $user = $this->orgUser();
        $this->actingAs($user);
        $templates = app(ProcedureTemplateService::class);
        $template = $templates->create($this->organization, $user, ['code' => 'FU-' . uniqid(), 'name' => 'Wartung Heizung']);
        $version = $template->versions->first();
        $templates->addStepDef($version, ['code' => 'inspect', 'step_type' => ProcedureStepType::Confirm->value, 'label' => 'Prüfen', 'required' => true]);
        $templates->publish($version, $user);

        $entry = DiaryEntry::factory()->for($user)->create(['organization_id' => $this->organization->id]);
        $run = app(ProcedureExecutionService::class)->start($template->fresh(['versions.steps']), $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();

        return ['user' => $user, 'stepRun' => $stepRun];
    }
}
