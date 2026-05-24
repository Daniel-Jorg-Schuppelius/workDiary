<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseAdminControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseAdminControllerTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.license.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('admin.license.index'))
            ->assertForbidden();
    }

    public function test_index_renders_for_org_admin_with_three_sections(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.license.index'))
            ->assertOk()
            ->assertSee(__('Lizenz-Karte'))
            ->assertSee(__('Limits'))
            ->assertSee(__('Feature-Flags'));
    }

    public function test_index_shows_users_limit_card(): void {
        $admin = User::factory()->admin()->create();
        // Drei weitere User in derselben Org, um eine konkrete Zahl zu sehen.
        User::factory()->user()->count(3)->create(['organization_id' => $admin->organization_id]);

        $response = $this->actingAs($admin)->get(route('admin.license.index'));

        $response->assertOk()->assertSee(__('Nutzer'));
    }
}
