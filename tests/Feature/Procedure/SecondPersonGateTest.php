<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecondPersonGateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureRunEventType, ProcedureStepRunStatus, ProcedureStepType};
use App\Exceptions\ProcedureSecondPersonException;
use App\Models\{DiaryEntry, Organization, ProcedureStepRun, ProcedureTemplate, User};
use App\Services\Procedure\{ProcedureExecutionService, ProcedureTemplateService, SecondPersonGate};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondPersonGateTest extends TestCase {
    use RefreshDatabase;

    private ProcedureTemplateService $templates;

    private ProcedureExecutionService $executor;

    private SecondPersonGate $gate;

    protected function setUp(): void {
        parent::setUp();
        $this->templates = app(ProcedureTemplateService::class);
        $this->executor = app(ProcedureExecutionService::class);
        $this->gate = app(SecondPersonGate::class);
    }

    public function test_done_is_blocked_without_assigned_second_person(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makeTemplateWithSecondPersonStep($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();

        try {
            $this->executor->execute($stepRun, $user, ProcedureStepRunStatus::Done);
            $this->fail('Expected ProcedureSecondPersonException');
        } catch (ProcedureSecondPersonException $e) {
            $this->assertSame(ProcedureSecondPersonException::CODE_MISSING, $e->errorCode);
            $this->assertSame(ProcedureSecondPersonException::REASON_NOT_ASSIGNED, $e->reason);
        }
    }

    public function test_done_is_blocked_when_assigned_but_not_signed(): void {
        [$org, $user, $second] = $this->makeOrgAndTwoUsers();
        $template = $this->makeTemplateWithSecondPersonStep($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();

        $this->gate->take($stepRun, $second);

        try {
            $this->executor->execute($stepRun->fresh(), $user, ProcedureStepRunStatus::Done);
            $this->fail('Expected NOT_SIGNED');
        } catch (ProcedureSecondPersonException $e) {
            $this->assertSame(ProcedureSecondPersonException::REASON_NOT_SIGNED, $e->reason);
        }
    }

    public function test_self_take_is_blocked_when_executor_equals_taker(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makeTemplateWithSecondPersonStep($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();
        $stepRun->executed_by_user_id = $user->id;
        $stepRun->save();

        try {
            $this->gate->take($stepRun->fresh(), $user);
            $this->fail('Expected self-exclusion');
        } catch (ProcedureSecondPersonException $e) {
            $this->assertSame(ProcedureSecondPersonException::CODE_SELF_NOT_ALLOWED, $e->errorCode);
        }
    }

    public function test_full_request_take_sign_then_done(): void {
        [$org, $user, $second] = $this->makeOrgAndTwoUsers();
        $template = $this->makeTemplateWithSecondPersonStep($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();

        $this->gate->request($stepRun, $user);
        $this->gate->take($stepRun->fresh(), $second);
        $this->gate->sign($stepRun->fresh(), $second);

        $done = $this->executor->execute($stepRun->fresh(), $user, ProcedureStepRunStatus::Done);
        $this->assertSame(ProcedureStepRunStatus::Done, $done->status);

        $this->assertSame(1, $run->events()->where('event_type', ProcedureRunEventType::SecondPersonRequested->value)->count());
        $this->assertSame(1, $run->events()->where('event_type', ProcedureRunEventType::SecondPersonAssigned->value)->count());
        $this->assertSame(1, $run->events()->where('event_type', ProcedureRunEventType::SecondPersonSigned->value)->count());
    }

    public function test_sign_by_non_taker_is_rejected(): void {
        [$org, $user, $second] = $this->makeOrgAndTwoUsers();
        $template = $this->makeTemplateWithSecondPersonStep($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();

        $this->gate->take($stepRun, $second);

        try {
            $this->gate->sign($stepRun->fresh(), $user);
            $this->fail('Expected NOT_TAKER');
        } catch (ProcedureSecondPersonException $e) {
            $this->assertSame(ProcedureSecondPersonException::REASON_NOT_TAKER, $e->reason);
        }
    }

    public function test_revoke_resets_step_and_records_event(): void {
        [$org, $user, $second] = $this->makeOrgAndTwoUsers();
        $template = $this->makeTemplateWithSecondPersonStep($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();

        $this->gate->take($stepRun, $second);
        $this->gate->sign($stepRun->fresh(), $second);
        $this->executor->execute($stepRun->fresh(), $user, ProcedureStepRunStatus::Done);

        $reset = $this->gate->revoke($stepRun->fresh(), $user, 'Fehlerhaft');

        $this->assertNull($reset->second_person_signed_at);
        $this->assertSame(ProcedureStepRunStatus::Pending, $reset->status);
        $this->assertSame(1, $run->events()->where('event_type', ProcedureRunEventType::SecondPersonRevoked->value)->count());
    }

    public function test_freigabe_step_type_implies_second_person_requirement(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->templates->create($org, $user, [
            'code' => 'FREI-'.uniqid(),
            'name' => 'Freigabe-Vorlage',
        ]);
        $version = $template->versions->first();
        $this->templates->addStepDef($version, [
            'code' => 'release',
            'step_type' => ProcedureStepType::Freigabe->value,
            'label' => 'Freigabe',
        ]);
        $this->templates->publish($version, $user);
        $template = $template->fresh(['versions.steps']);

        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        /** @var ProcedureStepRun $stepRun */
        $stepRun = $run->stepRuns->first();

        try {
            $this->executor->execute($stepRun, $user, ProcedureStepRunStatus::Done);
            $this->fail('Freigabe step must require second person');
        } catch (ProcedureSecondPersonException $e) {
            $this->assertSame(ProcedureSecondPersonException::REASON_NOT_ASSIGNED, $e->reason);
        }
    }

    private function makeTemplateWithSecondPersonStep(Organization $org, User $user): ProcedureTemplate {
        $template = $this->templates->create($org, $user, [
            'code' => '4A-'.uniqid(),
            'name' => 'Vier-Augen-Vorlage',
        ]);
        $version = $template->versions->first();
        $this->templates->addStepDef($version, [
            'code' => 'critical',
            'step_type' => ProcedureStepType::Confirm->value,
            'label' => 'Kritischer Schritt',
            'requires_second_person' => true,
            'config' => [
                'second_person_self_exclusion' => true,
            ],
        ]);
        $this->templates->publish($version, $user);

        return $template->fresh(['versions.steps']);
    }

    /** @return array{0: Organization, 1: User} */
    private function makeOrgAndUser(): array {
        $user = User::factory()->geschaeftsfuehrung()->create();

        return [$user->organization, $user];
    }

    /** @return array{0: Organization, 1: User, 2: User} */
    private function makeOrgAndTwoUsers(): array {
        $user = User::factory()->geschaeftsfuehrung()->create();
        $second = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $user->organization_id,
        ]);

        return [$user->organization, $user, $second];
    }
}
