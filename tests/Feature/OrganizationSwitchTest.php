<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationSwitchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Http\Controllers\OrganizationSwitchController;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSwitchTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_admin_can_switch_active_organization(): void {
        $orgA = Organization::factory()->create(['name' => 'Org A']);
        $orgB = Organization::factory()->create(['name' => 'Org B']);
        $admin = User::factory()->admin()->create(['organization_id' => $orgA->id]);

        $this->actingAs($admin)
            ->post(route('admin.organizations.switch'), ['organization_id' => $orgB->id])
            ->assertRedirect();

        $this->assertSame($orgB->id, session(OrganizationSwitchController::SESSION_KEY));
    }

    public function test_non_admin_cannot_switch_organization(): void {
        $org = Organization::factory()->create();
        $user = User::factory()->user()->create(['organization_id' => $org->id]);
        $other = Organization::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.organizations.switch'), ['organization_id' => $other->id])
            ->assertForbidden();

        $this->assertNull(session(OrganizationSwitchController::SESSION_KEY));
    }

    public function test_session_override_is_applied_for_admin(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $orgA->id]);

        $this->actingAs($admin)
            ->post(route('admin.organizations.switch'), ['organization_id' => $orgB->id])
            ->assertRedirect();

        // Folgerequest: SetOrganizationContext muss orgB binden
        $this->actingAs($admin)
            ->withSession([OrganizationSwitchController::SESSION_KEY => $orgB->id])
            ->get(route('dashboard'));

        $this->assertTrue(app()->bound('currentOrganization'));
        $this->assertSame($orgB->id, app('currentOrganization')->id);
    }

    public function test_invalid_session_override_falls_back_to_user_org(): void {
        $orgA = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $orgA->id]);

        $this->actingAs($admin)
            ->withSession([OrganizationSwitchController::SESSION_KEY => 9999999])
            ->get(route('dashboard'));

        $this->assertTrue(app()->bound('currentOrganization'));
        $this->assertSame($orgA->id, app('currentOrganization')->id);
    }

    public function test_clear_override_removes_session_value(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $orgA->id]);

        $this->actingAs($admin)
            ->withSession([OrganizationSwitchController::SESSION_KEY => $orgB->id])
            ->post(route('admin.organizations.switch'), ['organization_id' => null])
            ->assertRedirect();

        $this->assertNull(session(OrganizationSwitchController::SESSION_KEY));
    }
}
