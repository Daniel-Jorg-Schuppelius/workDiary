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
use App\Models\{InventoryOutboxEntry, StockMovement};
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
