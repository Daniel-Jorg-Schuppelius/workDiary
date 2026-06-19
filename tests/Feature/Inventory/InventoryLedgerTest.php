<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryLedgerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{InventoryMode, OwnershipType, ProviderCapability, StockState};
use App\Models\{Article, ArticleVariant, Organization, StockMovement, Warehouse};
use App\Services\Inventory\{InventoryLedger, InventoryProviderResolver, LocalInventoryProvider};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lokaler Lagerkern (Feature 048, MVP-066/067): abgeleitete Bestände aus dem
 * append-only Journal, Verfügbarkeitsformel, Negativsperre, getrennte
 * Eigentumsarten, Idempotenz, Unveränderlichkeit, Provider-Auflösung und
 * Mandantengrenze.
 */
final class InventoryLedgerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private InventoryLedger $ledger;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->ledger = app(InventoryLedger::class);
        $this->variant = $this->makeVariant();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_receipt_increases_available_and_physical_balance(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');

        $this->assertSame('10.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame('10.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    public function test_issue_reduces_and_blocks_negative_unless_allowed(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->ledger->issue($this->variant, $this->warehouse, '4');
        $this->assertSame('6.0000', $this->ledger->available($this->variant, $this->warehouse));

        try {
            $this->ledger->issue($this->variant, $this->warehouse, '100');
            $this->fail('Abgang über den verfügbaren Bestand muss blockiert werden.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->ledger->issue($this->variant, $this->warehouse, '100', allowNegative: true);
        $this->assertSame('-94.0000', $this->ledger->available($this->variant, $this->warehouse));
    }

    public function test_reservation_reduces_available_and_release_restores(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->ledger->reserve($this->variant, $this->warehouse, '3');

        $this->assertSame('7.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame('3.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Reserved));

        $this->ledger->releaseReservation($this->variant, $this->warehouse, '3');
        $this->assertSame('10.0000', $this->ledger->available($this->variant, $this->warehouse));
    }

    public function test_reserve_beyond_available_throws(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '2');

        $this->expectException(RuntimeException::class);
        $this->ledger->reserve($this->variant, $this->warehouse, '5');
    }

    public function test_ownership_buckets_are_separate(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10', OwnershipType::Own);
        $this->ledger->receipt($this->variant, $this->warehouse, '5', OwnershipType::Customer);

        $this->assertSame('10.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical, OwnershipType::Own));
        $this->assertSame('5.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical, OwnershipType::Customer));
    }

    public function test_movements_are_append_only(): void {
        $movement = $this->ledger->receipt($this->variant, $this->warehouse, '1');

        try {
            $movement->update(['qty_base' => '99']);
            $this->fail('Update einer Lagerbewegung muss blockiert sein.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->expectException(RuntimeException::class);
        $movement->delete();
    }

    public function test_idempotent_posting_prevents_double_booking(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10', idempotencyKey: 'wh-1');
        $this->ledger->receipt($this->variant, $this->warehouse, '10', idempotencyKey: 'wh-1');

        $this->assertSame('10.0000', $this->ledger->available($this->variant, $this->warehouse));
        $this->assertSame(1, StockMovement::query()->where('idempotency_key', 'wh-1')->count());
    }

    public function test_resolver_defaults_to_local_and_exposes_capabilities(): void {
        $resolver = app(InventoryProviderResolver::class);

        $this->assertSame(InventoryMode::Local, $resolver->modeFor($this->organization));
        $provider = $resolver->providerFor($this->organization);
        $this->assertInstanceOf(LocalInventoryProvider::class, $provider);
        $this->assertTrue($provider->supports(ProviderCapability::Reserve));
    }

    public function test_resolver_throws_for_external_mode_without_plugin(): void {
        $this->organization->update(['settings' => ['inventory_mode' => 'external']]);

        $this->expectException(RuntimeException::class);
        app(InventoryProviderResolver::class)->providerFor($this->organization->fresh());
    }

    public function test_movements_are_isolated_per_organization(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $this->assertSame(1, StockMovement::query()->count());

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, StockMovement::query()->count(), 'Fremd-Org sieht keine Bewegungen');
    }

    private function makeVariant(): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
        ]);
    }
}
