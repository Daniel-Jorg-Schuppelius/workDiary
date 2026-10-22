<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningRuleEnrollmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Automation\Triggers\UserCreatedTrigger;
use App\Enums\Asset\AssetStatus;
use App\Enums\Learning\{LearningEnrollmentSource, LearningUnitKind};
use App\Enums\User\UserRole;
use App\Models\Asset\Asset;
use App\Models\Automation\AutomationRule;
use App\Models\Learning\{LearningCourse, LearningEnrollment};
use App\Models\Platform\User;
use App\Services\Asset\AssetAssignmentService;
use App\Services\Asset\Automation\AssetCheckedOutTrigger;
use App\Services\Learning\Automation\EnrollInCourseAction;
use App\Services\Learning\LearningCourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Zuweisungsweg 5: Einschreibung über Automationsregeln (MVP-1273). */
final class LearningRuleEnrollmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LearningCourse $course;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $courses = app(LearningCourseService::class);
        $this->course = $courses->createCourse($this->organization, null, ['title' => 'Onboarding']);
        $courses->addUnit($this->course, ['title' => 'Willkommen', 'kind' => LearningUnitKind::Content->value]);
        $courses->release($this->course->refresh(), null);
    }

    /** @param array<string, mixed> $conditions */
    private function rule(string $trigger, array $conditions = ['all' => []]): AutomationRule {
        return AutomationRule::create([
            'organization_id' => $this->organization->id,
            'name' => 'Onboarding zuweisen',
            'trigger_event' => $trigger,
            'conditions' => $conditions,
            'actions' => [['type' => EnrollInCourseAction::TYPE, 'params' => ['target' => $this->course->sqid]]],
            'is_active' => true,
            'priority' => 10,
        ]);
    }

    public function test_new_member_with_matching_role_is_enrolled(): void {
        $this->rule(UserCreatedTrigger::KEY, ['all' => [['field' => 'role', 'op' => '=', 'value' => UserRole::User->value]]]);

        $this->actingAs($this->orgAdmin())->post(route('org.members.store'), [
            'name' => 'Neue Person',
            'email' => 'neu@test.de',
            'role' => UserRole::User->value,
            'password' => 'Password123!Strong',
            'password_confirmation' => 'Password123!Strong',
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'neu@test.de')->firstOrFail();
        $enrollment = LearningEnrollment::query()->where('user_id', $user->id)->sole();
        $this->assertSame((int) $this->course->id, (int) $enrollment->learning_course_id);
        $this->assertSame(LearningEnrollmentSource::Rule, $enrollment->source);
    }

    public function test_role_condition_filters_and_checked_out_device_enrolls_its_holder(): void {
        $this->rule(UserCreatedTrigger::KEY, ['all' => [['field' => 'role', 'op' => '=', 'value' => UserRole::Teamleitung->value]]]);
        $this->rule(AssetCheckedOutTrigger::KEY, ['all' => [['field' => 'category_code', 'op' => '=', 'value' => 'HAB']]]);
        $holder = $this->orgUser();
        $this->assertSame(0, LearningEnrollment::query()->count());

        $assets = app(AssetAssignmentService::class);
        $other = Asset::factory()->create(['organization_id' => $this->organization->id, 'status' => AssetStatus::Active->value, 'category_code' => 'BOHR']);
        $assets->checkOut($other, $this->orgAdmin(), $holder);
        $this->assertSame(0, LearningEnrollment::query()->count());

        $platform = Asset::factory()->create(['organization_id' => $this->organization->id, 'status' => AssetStatus::Active->value, 'category_code' => 'HAB']);
        $assets->checkOut($platform, $this->orgAdmin(), $holder);
        $this->assertSame((int) $holder->id, (int) LearningEnrollment::query()->sole()->user_id);
    }

    public function test_rule_form_offers_courses_as_target_and_stores_the_choice(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('admin.automations.create'))
            ->assertOk()
            ->assertSee(__('automation.action.learning_enroll'))
            ->assertSee('value="' . $this->course->sqid . '"', false);

        $this->actingAs($admin)->post(route('admin.automations.store'), [
            'name' => 'Ohne Kurs',
            'trigger_event' => UserCreatedTrigger::KEY,
            'action_type' => EnrollInCourseAction::TYPE,
            'conditions' => '{"all":[]}',
        ])->assertSessionHasErrors('action_target');

        $this->actingAs($admin)->post(route('admin.automations.store'), [
            'name' => 'Onboarding',
            'trigger_event' => UserCreatedTrigger::KEY,
            'action_type' => EnrollInCourseAction::TYPE,
            'action_target' => $this->course->sqid,
            'conditions' => '{"all":[]}',
        ])->assertRedirect(route('admin.automations.index'));

        $rule = AutomationRule::query()->where('name', 'Onboarding')->firstOrFail();
        $this->assertSame([['type' => EnrollInCourseAction::TYPE, 'params' => ['target' => $this->course->sqid]]], $rule->actions);
    }
}
