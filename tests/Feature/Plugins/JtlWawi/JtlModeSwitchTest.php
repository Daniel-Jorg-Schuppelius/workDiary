<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlModeSwitchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\JtlWawi;

use App\Models\{Article, ArticleVariant, ExternalArticleMapping, JtlConnection, JtlWarehouseMapping, StockMovement, User, Warehouse};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 078, MVP-324: Moduswechsel der Bestandsführung — Preflight
 * (Verbindung + Lager-Zuordnung), Audit, idempotente Übernahme-Inventur
 * beim Rückwechsel auf lokal und die Trennsperre bei externer Führung.
 */
final class JtlModeSwitchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://wawi.example.test:5883/api/eazybusiness';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        SpatiePermission::findOrCreate('inventory.configure', 'web');
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->admin->givePermissionTo('inventory.configure');
    }

    public function test_switch_to_external_requires_connection_and_mapping(): void {
        $this->actingAs($this->admin)
            ->post(route('admin.jtl.mode.update'), ['inventory_mode' => 'external'])
            ->assertSessionHas('error');

        $this->makeActiveConnection();
        $this->actingAs($this->admin)
            ->post(route('admin.jtl.mode.update'), ['inventory_mode' => 'external'])
            ->assertSessionHas('error');

        $this->assertNotSame('external', data_get($this->organization->refresh()->settings, 'inventory_mode'));
    }

    public function test_switch_to_external_persists_settings_and_audits(): void {
        $this->makeActiveConnection();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        JtlWarehouseMapping::query()->create([
            'organization_id' => $this->organization->id,
            'jtl_warehouse_id' => 'WH-M1',
            'name' => 'Hauptlager',
            'warehouse_id' => $warehouse->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.jtl.mode.update'), ['inventory_mode' => 'external'])
            ->assertSessionHas('success');

        $settings = $this->organization->refresh()->settings;
        $this->assertSame('external', data_get($settings, 'inventory_mode'));
        $this->assertSame('jtl_wawi', data_get($settings, 'inventory_plugin_id'));

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'inventory.mode_changed',
        ]);
    }

    public function test_switch_back_to_local_books_idempotent_takeover_corrections(): void {
        $this->makeActiveConnection();
        $this->organization->forceFill([
            'settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => 'jtl_wawi'],
        ])->save();

        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'SKU-M1']);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        ExternalArticleMapping::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'jtl_wawi',
            'external_id' => 'ITEM-M1',
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
        ]);
        JtlWarehouseMapping::query()->create([
            'organization_id' => $this->organization->id,
            'jtl_warehouse_id' => 'WH-M1',
            'name' => 'Hauptlager',
            'warehouse_id' => $warehouse->id,
        ]);

        FakePluginHttp::fake([
            self::BASE . '/v2/stocks*' => FakePluginHttp::response([
                'items' => [['itemId' => 'ITEM-M1', 'warehouseId' => 'WH-M1', 'quantityTotal' => 7]],
                'hasNextPage' => false,
            ]),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.jtl.mode.update'), ['inventory_mode' => 'local'])
            ->assertSessionHas('success');

        $this->assertSame('local', data_get($this->organization->refresh()->settings, 'inventory_mode'));

        $movement = StockMovement::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('idempotency_key', 'like', 'takeover:%')
            ->firstOrFail();
        $this->assertSame('7.0000', $movement->qty_base);

        // Übernahme darf NICHT in die Outbox zurückgespiegelt werden.
        $this->assertDatabaseMissing('inventory_outbox', ['idempotency_key' => $movement->idempotency_key]);

        // Wiederholung am selben Tag bucht nichts doppelt.
        $this->actingAs($this->admin)->post(route('admin.jtl.takeover'))->assertSessionHas('success');
        $this->assertSame(1, StockMovement::query()->where('idempotency_key', 'like', 'takeover:%')->count());
    }

    public function test_disconnect_is_blocked_while_external_mode_is_active(): void {
        $connection = $this->makeActiveConnection();
        $this->organization->forceFill([
            'settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => 'jtl_wawi'],
        ])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.jtl.connection.disconnect'))
            ->assertSessionHas('error');

        $this->assertSame(JtlConnection::STATUS_ACTIVE, $connection->refresh()->status);
        $this->assertNotNull($connection->api_key);
    }

    private function makeActiveConnection(): JtlConnection {
        return JtlConnection::query()->create([
            'organization_id' => $this->organization->id,
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'base_url' => self::BASE,
            'api_version' => '2.0',
            'allow_private_network' => true,
            'api_key' => 'KEY-TEST',
            'status' => JtlConnection::STATUS_ACTIVE,
        ]);
    }
}
