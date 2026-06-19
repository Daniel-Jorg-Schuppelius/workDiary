<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehouseManagementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\StockState;
use App\Models\{Article, ArticleVariant, User, Warehouse};
use App\Services\Inventory\InventoryLedger;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lager-Admin-UI (Feature 048, MVP-067): Lagerort-CRUD-Berechtigungen,
 * Löschschutz bei Bewegungen und manuelle Bestandsbuchung über den Ledger.
 */
final class WarehouseManagementTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private User $teamlead; // inventory.viewAny + inventory.post, KEIN configure

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_requires_view_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('warehouses.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('warehouses.index'))->assertOk();
    }

    public function test_create_requires_configure_permission(): void {
        // Teamleitung darf sehen + buchen, aber NICHT Lagerorte verwalten.
        $this->actingAs($this->teamlead)->post(route('warehouses.store'), [
            'name' => 'Halle 1', 'active' => '1',
        ])->assertForbidden();

        $this->actingAs($this->admin)->post(route('warehouses.store'), [
            'name' => 'Halle 1', 'active' => '1',
        ])->assertRedirect(route('warehouses.index'));

        $this->assertDatabaseHas('warehouses', ['name' => 'Halle 1', 'organization_id' => $this->organization->id]);
    }

    public function test_stock_overview_renders(): void {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $variant = $this->makeVariant();
        app(InventoryLedger::class)->receipt($variant, $warehouse, '4');

        $this->actingAs($this->admin)
            ->get(route('inventory.stock', ['warehouse' => $warehouse->sqid]))
            ->assertOk()
            ->assertSee($warehouse->name);
    }

    public function test_delete_blocked_when_movements_exist(): void {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $variant = $this->makeVariant();
        app(InventoryLedger::class)->receipt($variant, $warehouse, '3');

        $this->actingAs($this->admin)->delete(route('warehouses.destroy', $warehouse))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    public function test_post_movement_updates_balance(): void {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $variant = $this->makeVariant();

        $this->actingAs($this->teamlead)->post(route('inventory.movements.store'), [
            'warehouse' => $warehouse->sqid,
            'variant' => $variant->sqid,
            'movement' => 'receipt',
            'qty' => '7',
            'ownership' => 'own',
        ])->assertRedirect();

        $this->assertSame('7.0000', app(InventoryLedger::class)->balance($variant, $warehouse, StockState::Physical));
    }

    public function test_post_movement_requires_post_permission(): void {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $variant = $this->makeVariant();
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('inventory.movements.store'), [
            'warehouse' => $warehouse->sqid,
            'variant' => $variant->sqid,
            'movement' => 'receipt',
            'qty' => '1',
            'ownership' => 'own',
        ])->assertForbidden();
    }

    public function test_insufficient_issue_redirects_with_error(): void {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $variant = $this->makeVariant();

        $this->actingAs($this->teamlead)->post(route('inventory.movements.store'), [
            'warehouse' => $warehouse->sqid,
            'variant' => $variant->sqid,
            'movement' => 'issue',
            'qty' => '5',
            'ownership' => 'own',
        ])->assertSessionHas('error');
    }

    private function makeVariant(): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }
}
