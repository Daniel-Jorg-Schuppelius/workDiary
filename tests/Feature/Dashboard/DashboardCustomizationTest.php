<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardCustomizationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Models\{User, UserDashboardWidget};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DashboardCustomizationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_customize_page_lists_available_widgets(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->get(route('dashboard.customize'));

        $response->assertOk();
        $response->assertSee(__('Lesezeichen'));
    }

    public function test_save_persists_order_and_visibility(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'widgets' => [
                ['key' => 'bookmarks', 'hidden' => '1'],
            ],
        ]);

        $response->assertRedirect(route('dashboard.customize'));

        $rows = UserDashboardWidget::where('user_id', $user->id)->orderBy('sort_order')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('bookmarks', $rows[0]->widget_key);
        $this->assertTrue($rows[0]->hidden);
    }

    public function test_save_ignores_unknown_widget_keys(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->post(route('dashboard.customize.save'), [
            'widgets' => [
                ['key' => 'does-not-exist', 'hidden' => '0'],
                ['key' => 'bookmarks', 'hidden' => '0'],
            ],
        ])->assertRedirect();

        $this->assertSame(1, UserDashboardWidget::where('user_id', $user->id)->count());
        $this->assertSame('bookmarks', UserDashboardWidget::where('user_id', $user->id)->first()->widget_key);
    }

    public function test_guest_redirected_to_login(): void {
        $this->get(route('dashboard.customize'))->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_widgets_for_empty_user(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
