<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonalOnboardingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Dashboard\WidgetRegistry;
use App\Models\Platform\User;
use App\Models\Time\TimeEntry;
use App\Services\Onboarding\PersonalOnboardingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-911: persönlicher Einstieg je Rolle mit erkannten und abgehakten Schritten. */
final class PersonalOnboardingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_steps_follow_the_role_and_detect_progress(): void {
        $this->setUpOrganization();
        $field = User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
        $accounting = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $resolver = app(PersonalOnboardingResolver::class);

        $fieldSteps = array_column($resolver->forUser($field)['steps'], 'code');
        $this->assertContains('expense.first', $fieldSteps);
        $this->assertContains('attendance.first', $fieldSteps);
        $this->assertNotContains('invoice.first', $fieldSteps);
        $this->assertContains('invoice.first', array_column($resolver->forUser($accounting)['steps'], 'code'));

        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $field->id]);
        $steps = collect($resolver->forUser($field)['steps'])->keyBy('code');
        $this->assertTrue($steps['time.first']['done']);
        $this->assertFalse($steps['time.first']['manual']);
        $this->assertTrue($steps['help.center']['manual']);

        $this->actingAs($field)->get(route('me.onboarding'))->assertOk()->assertSee(__('onboarding.personal.step.expense.first.title'));
        $this->assertTrue(app(WidgetRegistry::class)->availableFor($field)->has('personal-onboarding'));

        $this->actingAs($field)->post(route('me.onboarding.done', ['step' => 'help.center']))->assertRedirect();
        $this->assertTrue(collect($resolver->forUser($field->fresh())['steps'])->keyBy('code')['help.center']['done']);
        $this->actingAs($field)->post(route('me.onboarding.done', ['step' => 'unbekannt.x']))->assertNotFound();

        $this->actingAs($field)->post(route('me.onboarding.dismiss'))->assertRedirect();
        $this->assertFalse(app(WidgetRegistry::class)->availableFor($field->fresh())->has('personal-onboarding'));
    }
}
