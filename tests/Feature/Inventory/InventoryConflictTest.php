<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryConflictTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\StockState;
use App\Models\{Article, ArticleVariant, PendingExternalConflict, StockMovement, User, Warehouse};
use App\Services\Inventory\{InventoryConflictResolver, InventoryLedger};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-072: Auflösung kompensationspflichtiger Inventory-Outbox-Konflikte —
 * lokal belassen oder per Gegenbuchung ausgleichen.
 */
final class InventoryConflictTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private InventoryLedger $ledger;
    private Warehouse $warehouse;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->ledger = app(InventoryLedger::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $article->id,
        ]);
    }

    private function bookedMovement(): StockMovement {
        return $this->ledger->receipt($this->variant, $this->warehouse, '50');
    }

    private function conflictFor(StockMovement $movement): PendingExternalConflict {
        return PendingExternalConflict::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'jtl',
            'conflict_type' => 'inventory_outbox',
            'referenceable_type' => $movement->getMorphClass(),
            'referenceable_id' => $movement->id,
            'local_snapshot' => ['qty_base' => $movement->qty_base, 'stock_state' => $movement->stock_state->value],
            'remote_snapshot' => [],
            'status' => PendingExternalConflict::STATUS_OPEN,
        ]);
    }

    private function physical(): string {
        return $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical);
    }

    public function test_compensate_reverses_movement_and_closes_conflict(): void {
        $conflict = $this->conflictFor($this->bookedMovement());
        $this->assertSame('50.0000', $this->physical());

        app(InventoryConflictResolver::class)->compensate($conflict, null);

        $this->assertSame('0.0000', $this->physical()); // Gegenbuchung hebt auf
        $this->assertSame(PendingExternalConflict::STATUS_COMPENSATED, $conflict->fresh()->status);
    }

    public function test_keep_local_closes_conflict_without_reversal(): void {
        $conflict = $this->conflictFor($this->bookedMovement());

        app(InventoryConflictResolver::class)->keepLocal($conflict, null);

        $this->assertSame('50.0000', $this->physical()); // Bestand unverändert
        $this->assertSame(PendingExternalConflict::STATUS_RESOLVED_LOCAL, $conflict->fresh()->status);
    }

    public function test_compensate_rejects_already_resolved_conflict(): void {
        $conflict = $this->conflictFor($this->bookedMovement());
        $conflict->forceFill(['status' => PendingExternalConflict::STATUS_COMPENSATED])->save();

        $this->expectException(RuntimeException::class);
        app(InventoryConflictResolver::class)->compensate($conflict, null);
    }

    public function test_compensate_route_books_counter_movement(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $conflict = $this->conflictFor($this->bookedMovement());

        $this->actingAs($admin)
            ->post(route('inventory.conflicts.compensate', $conflict))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('0.0000', $this->physical());
        $this->assertSame(PendingExternalConflict::STATUS_COMPENSATED, $conflict->fresh()->status);
    }
}
