<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehouseBinTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockState, WarehouseKind};
use App\Models\{Article, ArticleVariant, Organization, User, Vehicle, Warehouse, WarehouseBin};
use App\Services\Inventory\InventoryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lagerplätze und Fahrzeuglager (Feature 048, MVP-706): Bin-CRUD per Sqid,
 * Buchung mit Platz und Saldo je Platz, Sperre blockiert Abgang, Fremd-Bin
 * wird abgelehnt, Lagerart mit Fahrzeugbezug.
 */
final class WarehouseBinTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private User $teamlead; // inventory.viewAny + inventory.post, KEIN configure
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Halle 1']);
        $this->variant = $this->makeVariant();
    }

    public function test_bin_crud_via_http_with_sqids(): void {
        // Verwalten braucht inventory.configure — Teamleitung darf sehen, aber nicht anlegen.
        $this->actingAs($this->teamlead)->post(route('warehouses.bins.store', $this->warehouse), [
            'code' => 'A-01', 'active' => '1',
        ])->assertForbidden();

        $this->actingAs($this->admin)->post(route('warehouses.bins.store', $this->warehouse), [
            'code' => 'A-01', 'name' => 'Regal A', 'sort_order' => '3', 'active' => '1',
        ])->assertRedirect(route('warehouses.bins.index', $this->warehouse));
        $this->assertDatabaseHas('warehouse_bins', [
            'warehouse_id' => $this->warehouse->id,
            'organization_id' => $this->organization->id,
            'code' => 'A-01',
            'sort_order' => 3,
        ]);

        // Kürzel je Lager eindeutig.
        $this->actingAs($this->admin)->from(route('warehouses.bins.index', $this->warehouse))
            ->post(route('warehouses.bins.store', $this->warehouse), ['code' => 'A-01', 'active' => '1'])
            ->assertSessionHasErrors('code');

        $bin = WarehouseBin::query()->where('code', 'A-01')->firstOrFail();
        $this->actingAs($this->teamlead)->get(route('warehouses.bins.index', $this->warehouse))
            ->assertOk()->assertSee('A-01')->assertSee('Regal A');

        $this->actingAs($this->admin)->put(route('warehouses.bins.update', [$this->warehouse, $bin]), [
            'code' => 'A-01', 'name' => 'Regal A neu', 'sort_order' => '1', 'active' => '1',
        ])->assertRedirect();
        $this->assertSame('Regal A neu', $bin->fresh()?->name);

        $this->actingAs($this->admin)->post(route('warehouses.bins.block', [$this->warehouse, $bin]))->assertRedirect();
        $this->assertTrue((bool) $bin->fresh()?->blocked);

        // Fremder Lagerkontext → 404 statt Zugriff über die Lagergrenze.
        $other = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin)->get(route('warehouses.bins.edit', [$other, $bin]))->assertNotFound();

        $this->actingAs($this->admin)->delete(route('warehouses.bins.destroy', [$this->warehouse, $bin]))->assertRedirect();
        $this->assertDatabaseMissing('warehouse_bins', ['id' => $bin->id]);
    }

    public function test_ledger_posts_bin_and_balances_by_bin(): void {
        $ledger = app(InventoryLedger::class);
        $a = $this->makeBin('A', 1);
        $b = $this->makeBin('B', 2);

        $ledger->receipt($this->variant, $this->warehouse, '5', bin: $a);
        $ledger->receipt($this->variant, $this->warehouse, '3', bin: $b);
        $ledger->receipt($this->variant, $this->warehouse, '2');

        $this->assertDatabaseHas('stock_movements', ['bin_id' => $a->id, 'qty_base' => '5.0000']);
        $this->assertSame([0 => '2.0000', $a->id => '5.0000', $b->id => '3.0000'], $ledger->balancesByBin($this->variant, $this->warehouse));
        $this->assertSame('10.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Physical));
        $this->assertSame('5.0000', $ledger->availableInBin($this->variant, $this->warehouse, $a));

        $ledger->issue($this->variant, $this->warehouse, '2', bin: $a);
        $this->assertSame('3.0000', $ledger->availableInBin($this->variant, $this->warehouse, $a));
        $this->assertSame('8.0000', $ledger->available($this->variant, $this->warehouse));

        // Abgang vom Platz zählt nur den Platzbestand — nicht das Nachbarregal.
        $this->expectException(RuntimeException::class);
        $ledger->issue($this->variant, $this->warehouse, '4', bin: $a);
    }

    public function test_blocked_bin_rejects_issue_but_keeps_stock(): void {
        $ledger = app(InventoryLedger::class);
        $a = $this->makeBin('A', 1);
        $ledger->receipt($this->variant, $this->warehouse, '5', bin: $a);

        $a->update(['blocked' => true]);
        $a->refresh();

        try {
            $ledger->issue($this->variant, $this->warehouse, '1', bin: $a);
            $this->fail('Gesperrter Lagerplatz muss den Abgang blockieren.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('A', $e->getMessage());
        }
        $this->assertSame('5.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Physical, bin: $a));

        // Inaktiver Platz ebenso; Korrektur (Inventur) bleibt möglich.
        $a->update(['blocked' => false, 'active' => false]);
        $a->refresh();
        $this->expectException(RuntimeException::class);
        $ledger->receipt($this->variant, $this->warehouse, '1', bin: $a);
    }

    public function test_correction_allowed_on_blocked_bin(): void {
        $ledger = app(InventoryLedger::class);
        $a = $this->makeBin('A', 1);
        $ledger->receipt($this->variant, $this->warehouse, '5', bin: $a);
        $a->update(['blocked' => true]);
        $a->refresh();

        $ledger->correction($this->variant, $this->warehouse, StockState::Physical, '-1', bin: $a);
        $this->assertSame('4.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Physical, bin: $a));
    }

    public function test_foreign_bin_is_rejected(): void {
        $other = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $other->id, 'code' => 'X']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('inventory.error.bin_foreign'));
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '1', bin: $foreign);
        $this->assertDatabaseMissing('stock_movements', ['bin_id' => $foreign->id]);
    }

    public function test_movement_store_with_bin_via_http(): void {
        $a = $this->makeBin('A', 1);

        $this->actingAs($this->teamlead)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid,
            'variant' => $this->variant->sqid,
            'movement' => 'receipt',
            'qty' => '7',
            'ownership' => 'own',
            'bin' => $a->sqid,
        ])->assertRedirect(route('inventory.stock', ['warehouse' => $this->warehouse->sqid]));
        $this->assertDatabaseHas('stock_movements', ['bin_id' => $a->id, 'qty_base' => '7.0000']);

        // Bestandsübersicht zeigt die Platzspalte mit Saldo je Platz.
        $this->actingAs($this->admin)
            ->get(route('inventory.stock', ['warehouse' => $this->warehouse->sqid]))
            ->assertOk()->assertSee('A: 7.0000');

        // Platz eines anderen Lagers → Fehler, keine Buchung.
        $other = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $other->id, 'code' => 'X']);
        $this->actingAs($this->teamlead)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid,
            'variant' => $this->variant->sqid,
            'movement' => 'receipt',
            'qty' => '1',
            'ownership' => 'own',
            'bin' => $foreign->sqid,
        ])->assertSessionHas('error');
        $this->assertDatabaseMissing('stock_movements', ['bin_id' => $foreign->id]);
    }

    public function test_vehicle_warehouse_carries_vehicle_reference(): void {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'label' => 'Montagebus', 'license_plate' => 'B-MB 1']);

        $this->actingAs($this->admin)->post(route('warehouses.store'), [
            'name' => 'Bus 1', 'kind' => WarehouseKind::Vehicle->value, 'vehicle_id' => $vehicle->sqid, 'active' => '1',
        ])->assertRedirect(route('warehouses.index'));
        $this->assertDatabaseHas('warehouses', ['name' => 'Bus 1', 'kind' => 'vehicle', 'vehicle_id' => $vehicle->id]);

        $warehouse = Warehouse::query()->where('name', 'Bus 1')->firstOrFail();
        $this->assertSame(WarehouseKind::Vehicle, $warehouse->kind);
        $this->assertSame('Montagebus (B-MB 1)', $warehouse->referenceLabel());
        $this->actingAs($this->admin)->get(route('warehouses.index'))->assertOk()->assertSee('Montagebus');

        // Art „fest" verwirft einen mitgesendeten Bezug; Altaufrufer ohne kind bleiben fest.
        $this->actingAs($this->admin)->put(route('warehouses.update', $warehouse), [
            'name' => 'Bus 1', 'kind' => WarehouseKind::Fixed->value, 'vehicle_id' => $vehicle->sqid, 'active' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'kind' => 'fixed', 'vehicle_id' => null]);

        // Fahrzeug einer fremden Organisation ist kein gültiger Bezug (org-gescopte Rule).
        $otherOrg = Organization::factory()->create();
        $foreignVehicle = Vehicle::factory()->create(['organization_id' => $otherOrg->id]);
        $this->actingAs($this->admin)->from(route('warehouses.index'))->post(route('warehouses.store'), [
            'name' => 'Bus 2', 'kind' => WarehouseKind::Vehicle->value, 'vehicle_id' => $foreignVehicle->sqid, 'active' => '1',
        ])->assertSessionHasErrors('vehicle_id');
        $this->assertDatabaseMissing('warehouses', ['name' => 'Bus 2']);
    }

    public function test_warehouse_with_movements_in_bin_cannot_delete_bin(): void {
        $a = $this->makeBin('A', 1);
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '1', bin: $a);

        $this->actingAs($this->admin)->delete(route('warehouses.bins.destroy', [$this->warehouse, $a]))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('warehouse_bins', ['id' => $a->id]);
    }

    private function makeBin(string $code, int $sortOrder): WarehouseBin {
        return WarehouseBin::factory()->create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $this->warehouse->id,
            'code' => $code,
            'sort_order' => $sortOrder,
        ]);
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
