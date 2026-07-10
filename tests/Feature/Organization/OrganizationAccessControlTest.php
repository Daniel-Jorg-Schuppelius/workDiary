<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationAccessControlTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Organization;

use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-Tenant-Absicherung der Organisationsverwaltung (Whitebox 2026-07):
 * `Organization` trägt keinen OrganizationScope, das Route-Binding löst
 * jeden Mandanten global auf. Nur der globale Plattform-Betreiber darf die
 * Mandantenliste/Export/Deaktivierung/Purge einer FREMDEN Org auslösen; ein
 * org-lokaler Admin bleibt auf die EIGENE Organisation beschränkt.
 */
class OrganizationAccessControlTest extends TestCase {
    use RefreshDatabase;

    public function test_org_local_admin_cannot_reach_tenant_list(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.organizations.index'))->assertForbidden();
    }

    public function test_platform_admin_can_reach_tenant_list(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get(route('admin.organizations.index'))->assertOk();
    }

    public function test_org_local_admin_cannot_touch_foreign_org(): void {
        $admin = User::factory()->admin()->create();
        $foreign = Organization::factory()->create(['name' => 'Fremd AG']);

        $this->actingAs($admin)->get(route('admin.organizations.edit', $foreign))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.organizations.export', $foreign))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.organizations.deactivate', $foreign))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.organizations.purge', $foreign))->assertForbidden();

        $this->assertDatabaseHas('organizations', ['id' => $foreign->id, 'is_active' => true]);
    }

    public function test_org_local_admin_can_edit_own_org(): void {
        $admin = User::factory()->admin()->create();
        $own = Organization::query()->findOrFail($admin->organization_id);

        $this->actingAs($admin)->get(route('admin.organizations.edit', $own))->assertOk();

        $this->actingAs($admin)->put(route('admin.organizations.update', $own), [
            'name' => 'Eigen umbenannt',
            'plan' => $own->plan,
            'locale' => $own->locale ?? 'de',
            'timezone' => $own->timezone ?? 'Europe/Berlin',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('Eigen umbenannt', $own->refresh()->name);
    }

    public function test_platform_admin_can_export_foreign_org(): void {
        $admin = User::factory()->platformAdmin()->create();
        $foreign = Organization::factory()->create();

        // Kein 403 mehr (Export erzeugt einen Download-Response).
        $this->actingAs($admin)->post(route('admin.organizations.export', $foreign))
            ->assertStatus(200);
    }
}
