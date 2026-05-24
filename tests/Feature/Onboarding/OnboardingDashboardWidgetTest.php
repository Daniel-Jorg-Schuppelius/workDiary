<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnboardingDashboardWidgetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Onboarding;

use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingDashboardWidgetTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_widget_is_visible_on_dashboard_for_org_admin(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('onboarding.widget.title'))
            ->assertSee(__('onboarding.widget.open_link'));
    }

    public function test_widget_is_hidden_for_regular_user_without_permission(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('onboarding.widget.title'))
            ->assertDontSee(__('onboarding.widget.open_link'));
    }

    public function test_widget_is_hidden_when_dismissed(): void {
        $admin = User::factory()->admin()->create();

        $organization = Organization::query()->findOrFail($admin->organization_id);
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $settings['ui'] = array_merge(
            is_array($settings['ui'] ?? null) ? $settings['ui'] : [],
            ['onboarding_widget_dismissed_at' => CarbonImmutable::now()->toIso8601String()]
        );
        $organization->settings = $settings;
        $organization->save();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('onboarding.widget.title'))
            ->assertDontSee(__('onboarding.widget.open_link'));
    }
}
