<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanModuleGatingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hartes Modul-Gating (EnforcePlanModules): Routen eines Moduls sind nur
 * erreichbar, wenn der Plan/Lizenz das Modul enthaelt. Ohne nutzbare Lizenz
 * steuert organizations.plan ueber config/plans.php.
 */
class PlanModuleGatingTest extends TestCase {
    use RefreshDatabase;

    private function userFor(Organization $org): User {
        return User::factory()->create(['organization_id' => $org->id]);
    }

    public function test_free_plan_blocks_pro_module_route(): void {
        $org = Organization::factory()->free()->create();

        $this->actingAs($this->userFor($org))
            ->get(route('vehicles.index'))
            ->assertStatus(423)
            ->assertSee('Fuhrpark'); // lesbares Label, nicht der rohe Code module.fuhrpark
    }

    public function test_enterprise_plan_allows_pro_module_route(): void {
        $org = Organization::factory()->enterprise()->create();

        $response = $this->actingAs($this->userFor($org))->get(route('vehicles.index'));

        $this->assertNotSame(423, $response->status(), 'Enterprise enthaelt das Modul – kein 423.');
    }

    public function test_core_route_is_not_gated_even_on_free(): void {
        $org = Organization::factory()->free()->create();

        $response = $this->actingAs($this->userFor($org))->get(route('dashboard'));

        $this->assertNotSame(423, $response->status(), 'Core-Routen sind nie modul-gegatet.');
    }

    public function test_free_plan_blocks_compliance_module(): void {
        $org = Organization::factory()->free()->create();

        $this->actingAs($this->userFor($org))
            ->get(route('whistleblowing.internal.index'))
            ->assertStatus(423);
    }

    public function test_menu_hides_items_without_view_permission(): void {
        $org = Organization::factory()->enterprise()->create();
        $user = $this->userFor($org); // einfacher Nutzer ohne AssetView
        $nav = app(\App\Services\Navigation\NavGate::class);

        $this->actingAs($user);
        $this->assertTrue($nav->mayAccess('customers.index'), 'viewAny=true → sichtbar');
        $this->assertFalse($nav->mayAccess('assets.index'), 'AssetView fehlt → versteckt');
        $this->assertTrue($nav->mayAccess('dashboard'), 'ungemappte Core-Route → sichtbar');
    }

    public function test_admin_bypasses_view_permission_in_menu(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin);
        $this->assertTrue(
            app(\App\Services\Navigation\NavGate::class)->mayAccess('assets.index'),
            'Admin sieht via Policy-Bypass alles'
        );
    }

    public function test_free_plan_blocks_payroll_module(): void {
        $org = Organization::factory()->free()->create();

        // Gate greift vor der Permission-Pruefung des Controllers.
        $this->actingAs($this->userFor($org))
            ->get(route('payroll.index'))
            ->assertStatus(423);
    }

    public function test_free_hides_kanban_item_and_team_reports_in_menu(): void {
        $org = Organization::factory()->free()->create();

        $response = $this->actingAs($this->userFor($org))->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('kanban.index'), false);
        $response->assertDontSee(route('reports.coverage'), false); // Team-Auswertung
    }

    public function test_enterprise_shows_kanban_item_and_team_reports_in_menu(): void {
        $org = Organization::factory()->enterprise()->create();

        $response = $this->actingAs($this->userFor($org))->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('kanban.index'), false);
        $response->assertSee(route('reports.coverage'), false);
    }

    public function test_navigation_hides_gated_module_links_on_free(): void {
        $org = Organization::factory()->free()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('shift-types.index'), false);      // Planung
        $response->assertDontSee(route('event-categories.index'), false); // Vertrieb
        $response->assertDontSee(route('rooms.index'), false);            // Liegenschaften
        $response->assertDontSee(route('materials.index'), false);       // Vertrieb (Abrechnungskatalog)
    }

    public function test_navigation_shows_gated_module_links_on_enterprise(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('shift-types.index'), false);
        $response->assertSee(route('event-categories.index'), false);
        $response->assertSee(route('rooms.index'), false);
        $response->assertSee(route('materials.index'), false);
    }
}
