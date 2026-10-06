<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalStockMirrorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\OutboxStatus;
use App\Models\Inventory\{InventoryOutboxEntry, StockMovement};
use App\Services\Inventory\{ExternalStockMirror, InventoryOutboxService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Externer Schreibpfad (Feature 048, MVP-072): lokal gebuchte Bewegungen werden
 * nur bei externer Bestandsführung gespiegelt; die Inbound-Bestätigung schließt
 * den Eintrag idempotent ab.
 */
final class ExternalStockMirrorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_mirror_enqueues_when_organization_is_external(): void {
        Bus::fake();
        $this->organization->update(['settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => 'jtl_wawi']]);
        $movement = StockMovement::factory()->create(['organization_id' => $this->organization->id]);

        app(ExternalStockMirror::class)->mirror($movement, $this->organization->fresh());

        $this->assertSame(1, InventoryOutboxEntry::query()->count());
        $this->assertSame('jtl_wawi', InventoryOutboxEntry::query()->first()?->plugin_id);
    }

    /** Eine Umbuchung zwischen Chargen ändert den Bestand je Variante und Lager nicht — das Fremdsystem erfährt nichts davon. */
    public function test_lot_merge_is_not_mirrored(): void {
        Bus::fake();
        $this->organization->update(['settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => 'jtl_wawi']]);
        $warehouse = \App\Models\Inventory\Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = \App\Models\Article\Article::factory()->create(['organization_id' => $this->organization->id, 'batch_required' => true]);
        $variant = \App\Models\Article\ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'option_signature' => 'default']);
        $lots = app(\App\Services\Inventory\LotService::class);
        $target = $lots->register($variant, 'L-ZIEL');
        $source = $lots->register($variant, 'L-QUELLE');
        $lots->receiveIntoLot($variant, $warehouse, '6', '2', $target);
        $lots->receiveIntoLot($variant, $warehouse, '5', '3', $source);
        $this->assertSame(2, InventoryOutboxEntry::query()->count());

        app(\App\Services\Inventory\LotSplitService::class)->merge($source, $target);

        $this->assertSame(4, StockMovement::query()->count());
        $this->assertSame(2, InventoryOutboxEntry::query()->count());
    }

    /** Teilen, Sperren und die Reparaturpaarung ändern den Bestand je Variante und Lager nicht — nichts davon wird gespiegelt. */
    public function test_lot_split_block_and_repair_are_not_mirrored(): void {
        Bus::fake();
        $this->organization->update(['settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => 'jtl_wawi']]);
        $warehouse = \App\Models\Inventory\Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = \App\Models\Article\Article::factory()->create(['organization_id' => $this->organization->id, 'batch_required' => true]);
        $variant = \App\Models\Article\ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'option_signature' => 'default']);
        $lots = app(\App\Services\Inventory\LotService::class);
        $lot = $lots->register($variant, 'L-QUELLE');
        $lots->receiveIntoLot($variant, $warehouse, '6', '2', $lot);
        $this->assertSame(1, InventoryOutboxEntry::query()->count());

        $part = app(\App\Services\Inventory\LotSplitService::class)->split($lot, '2', 'L-TEIL');
        $lots->detach($part, '1');
        $lots->block($lot, 'Rückruf', \App\Models\Platform\User::factory()->create(['organization_id' => $this->organization->id]));

        // Zugang, Teilen (2), Reparaturpaarung (2), Sperre.
        $this->assertSame(6, StockMovement::query()->count());
        $this->assertSame(1, InventoryOutboxEntry::query()->count());
    }

    public function test_mirror_is_noop_when_local(): void {
        Bus::fake();
        $movement = StockMovement::factory()->create(['organization_id' => $this->organization->id]);

        app(ExternalStockMirror::class)->mirror($movement, $this->organization);

        $this->assertSame(0, InventoryOutboxEntry::query()->count());
    }

    public function test_confirm_by_key_is_idempotent(): void {
        Bus::fake();
        $outbox = app(InventoryOutboxService::class);
        $entry = $outbox->enqueue($this->organization->id, 'jtl_wawi', 'receipt', ['x' => 1], 'K-1');

        $this->assertTrue($outbox->confirmByKey($this->organization->id, 'K-1'));
        $this->assertTrue($outbox->confirmByKey($this->organization->id, 'K-1')); // idempotent
        $this->assertFalse($outbox->confirmByKey($this->organization->id, 'UNKNOWN'));
        $this->assertSame(OutboxStatus::Confirmed, $entry->fresh()->status);
    }
}
