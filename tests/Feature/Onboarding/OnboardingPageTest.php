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

use App\Models\User;
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
            ->assertSee(__('Onboarding-Checkliste'))
            ->assertSee(__('Fortschritt'))
            ->assertSee('org.profile');
    }
}
