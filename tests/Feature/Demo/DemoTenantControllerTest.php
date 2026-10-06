<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoTenantControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTenantControllerTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.demo.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.demo.index'))->assertForbidden();
    }

    public function test_index_renders_for_org_admin(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.demo.index'))
            ->assertOk()
            ->assertSee(__('Demo-Mandant'))
            ->assertSee(__('Inhalt des Demo-Mandanten'));
    }

    public function test_seed_creates_demo_data_writes_audit_and_marks_org(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->from(route('admin.demo.index'))
            ->post(route('admin.demo.seed'))
            ->assertRedirect(route('admin.demo.index'));

        $org = Organization::query()->findOrFail($admin->organization_id);
        $this->assertTrue($org->is_demo);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
            'event' => 'demo.seeded',
        ]);
    }

    public function test_reset_refuses_when_org_is_not_demo(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->from(route('admin.demo.index'))
            ->post(route('admin.demo.reset'))
            ->assertSessionHasErrors('organization');
    }

    public function test_reset_runs_when_org_is_demo_and_writes_audit(): void {
        $admin = User::factory()->platformAdmin()->create();
        $org = Organization::query()->findOrFail($admin->organization_id);
        $org->is_demo = true;
        $org->demo_seeded_at = now();
        $org->save();
        Customer::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $admin->id,
            'name' => 'Vorab-Kunde',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.demo.index'))
            ->post(route('admin.demo.reset'))
            ->assertRedirect(route('admin.demo.index'));

        $this->assertDatabaseMissing('customers', [
            'organization_id' => $org->id,
            'name' => 'Vorab-Kunde',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'event' => 'demo.reset',
        ]);
    }

    public function test_banner_visible_on_dashboard_when_organization_is_demo(): void {
        $admin = User::factory()->platformAdmin()->create();
        $org = Organization::query()->findOrFail($admin->organization_id);
        $org->is_demo = true;
        $org->demo_seeded_at = now();
        $org->save();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dies ist ein Demo-Mandant', false);
    }

    /** Der Knopf trug `:disabled` als wörtliches Attribut und war nie gesperrt. */
    public function test_seed_button_is_disabled_once_the_tenant_holds_data(): void {
        $admin = User::factory()->platformAdmin()->create();
        $seedButton = '~<button type="submit"([^>]*)>(?:(?!</button>).)*' . preg_quote(__('Demo-Daten erzeugen'), '~') . '~s';

        $html = (string) $this->actingAs($admin)->get(route('admin.demo.index'))->assertOk()->getContent();
        $this->assertSame(1, preg_match($seedButton, $html, $empty));
        $this->assertStringNotContainsString('disabled', $empty[1]);

        Customer::factory()->create(['organization_id' => $admin->organization_id]);

        $html = (string) $this->actingAs($admin)->get(route('admin.demo.index'))->assertOk()->getContent();
        $this->assertSame(1, preg_match($seedButton, $html, $filled));
        $this->assertStringContainsString(' disabled', $filled[1]);
        $this->assertStringNotContainsString(':disabled', $filled[1]);
    }

    public function test_seed_is_refused_once_the_tenant_holds_data(): void {
        $admin = User::factory()->platformAdmin()->create();
        Customer::factory()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)
            ->post(route('admin.demo.seed'), ['industry' => 'it'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse((bool) $admin->organization->refresh()->is_demo);
        $this->assertSame(1, Customer::query()->where('organization_id', $admin->organization_id)->count());
    }
}
