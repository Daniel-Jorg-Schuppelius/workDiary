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

use App\Models\Platform\{Organization, User};
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

    /** UI-Crawl 2026-09-19: Der Menüeintrag „Organisation" verlinkte die numerische ID → 404. */
    public function test_org_local_admin_menu_links_own_org_by_sqid(): void {
        $admin = User::factory()->admin()->create();
        $own = Organization::query()->findOrFail($admin->organization_id);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.organizations.edit', $own), false)
            ->assertDontSee('/admin/organizations/' . $own->id . '/edit', false);
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
        ])->assertRedirect(route('admin.organizations.edit', $own)); // nicht die Mandantenliste (403, UI-Fuzz 2026-09-21)

        $this->assertSame('Eigen umbenannt', $own->refresh()->name);
    }

    /**
     * Plan und Aktiv-Status setzt nur der Plattformbetrieb: Ein herabgesetzter
     * Plan startet Karenz und Purge der Moduldaten, „inaktiv" sperrt die
     * Organisation samt Admin aus (Hilfe-Lückenschluss 2026-10-08).
     */
    public function test_org_local_admin_cannot_change_plan_or_active_status(): void {
        $admin = User::factory()->admin()->create();
        $own = Organization::query()->findOrFail($admin->organization_id);
        $own->forceFill(['plan' => 'enterprise'])->save();

        $this->actingAs($admin)->get(route('admin.organizations.edit', $own))
            ->assertOk()
            ->assertDontSee('name="plan"', false)
            ->assertDontSee('name="is_active"', false)
            ->assertDontSee(__('Organisation wirklich löschen?'));

        $this->actingAs($admin)->put(route('admin.organizations.update', $own), [
            'name' => $own->name,
            'plan' => 'free',
            'locale' => $own->locale ?? 'de',
            'timezone' => $own->timezone ?? 'Europe/Berlin',
            'is_active' => 0,
        ])->assertRedirect();

        $own->refresh();
        $this->assertSame('enterprise', $own->plan);
        $this->assertTrue((bool) $own->is_active);
        $this->assertDatabaseMissing('plan_module_grace', ['organization_id' => $own->id]);
    }

    public function test_platform_admin_can_change_plan_and_active_status(): void {
        $admin = User::factory()->platformAdmin()->create();
        $org = Organization::factory()->create(['plan' => 'enterprise']);

        $this->actingAs($admin)->put(route('admin.organizations.update', $org), [
            'name' => $org->name,
            'plan' => 'pro',
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
            'is_active' => 0,
        ])->assertRedirect();

        $org->refresh();
        $this->assertSame('pro', $org->plan);
        $this->assertFalse((bool) $org->is_active);
    }

    public function test_platform_admin_can_export_foreign_org(): void {
        $admin = User::factory()->platformAdmin()->create();
        $foreign = Organization::factory()->create();

        // Kein 403 mehr (Export erzeugt einen Download-Response).
        $this->actingAs($admin)->post(route('admin.organizations.export', $foreign))
            ->assertStatus(200);
    }
}
