<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Onboarding;

use App\Models\{OnboardingProgress, Organization, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingPageTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_page_requires_authentication(): void {
        $this->get(route('onboarding.index'))->assertRedirect(route('login'));
    }

    public function test_page_is_forbidden_without_permission(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('onboarding.index'))
            ->assertForbidden();
    }

    public function test_page_renders_for_org_admin(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('onboarding.index'))
            ->assertOk()
            ->assertSee(__('onboarding.page.heading'))
            ->assertSee(__('onboarding.page.progress_label'))
            ->assertSee(__('onboarding.step.org.profile.title'))
            ->assertDontSee('org.profile');
    }

    public function test_skip_requires_permission(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->from(route('onboarding.index'))
            ->post(route('onboarding.steps.skip', ['step' => 'users.invite']), [
                'reason' => 'Vorübergehend alleiniger Admin',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_skip_non_hard_step_with_reason(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('onboarding.index'))
            ->post(route('onboarding.steps.skip', ['step' => 'users.invite']), [
                'reason' => 'Aktuell noch kein zweiter Account verfügbar',
            ])
            ->assertRedirect(route('onboarding.index'));

        $this->assertDatabaseHas('onboarding_progress', [
            'organization_id' => $admin->organization_id,
            'step_code' => 'users.invite',
            'state' => 'skipped',
            'done_by_user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'onboarding.stepSkipped',
            'auditable_type' => OnboardingProgress::class,
        ]);
    }

    public function test_hard_step_cannot_be_skipped(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('onboarding.index'))
            ->post(route('onboarding.steps.skip', ['step' => 'org.profile']), [
                'reason' => 'Sollte nicht erlaubt sein',
            ])
            ->assertSessionHasErrors('step');

        $this->assertDatabaseMissing('onboarding_progress', [
            'organization_id' => $admin->organization_id,
            'step_code' => 'org.profile',
            'state' => 'skipped',
        ]);
    }

    public function test_widget_dismiss_requires_permission(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->from(route('onboarding.index'))
            ->post(route('onboarding.widget.dismiss'))
            ->assertForbidden();
    }

    public function test_admin_can_dismiss_widget_and_audit_is_written(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('onboarding.index'))
            ->post(route('onboarding.widget.dismiss'))
            ->assertRedirect(route('onboarding.index'));

        $organization = Organization::query()->findOrFail($admin->organization_id);
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $ui = is_array($settings['ui'] ?? null) ? $settings['ui'] : [];

        $this->assertArrayHasKey('onboarding_widget_dismissed_at', $ui);
        $this->assertIsString($ui['onboarding_widget_dismissed_at']);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'onboarding.widgetDismissed',
            'auditable_type' => Organization::class,
            'auditable_id' => $admin->organization_id,
        ]);
    }
}
