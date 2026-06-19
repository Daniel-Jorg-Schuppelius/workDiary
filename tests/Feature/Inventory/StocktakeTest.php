<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StocktakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockCountStatus, StockState};
use App\Models\{Article, ArticleVariant, Organization, StockCount, StockMovement, Warehouse};
use App\Services\Inventory\{InventoryLedger, StocktakeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Stichtagsbezogene Inventur (Feature 048, MVP-069): Sollbestand einfrieren,
 * zählen, Differenz gegen den eingefrorenen Stand, Korrekturbuchung; Bewegungen
 * nach dem Zählzeitpunkt laufen separat weiter.
 */
final class StocktakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private InventoryLedger $ledger;
    private StocktakeService $stocktake;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->ledger = app(InventoryLedger::class);
        $this->stocktake = app(StocktakeService::class);
        $this->variant = $this->makeVariant();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_open_freezes_book_quantities(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');

        $count = $this->stocktake->open($this->warehouse);
        $this->assertSame(StockCountStatus::Counting, $count->status);

        $line = $count->lines->firstWhere('stock_state', StockState::Physical);
        $this->assertNotNull($line);
        $this->assertSame('10.0000', $line->book_qty);
        $this->assertNull($line->counted_qty);
    }

    public function test_record_count_computes_difference(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $count = $this->stocktake->open($this->warehouse);
        $line = $count->lines->firstWhere('stock_state', StockState::Physical);

        $this->stocktake->recordCount($line, '8');
        $this->assertSame('-2.0000', $line->fresh()->difference());
    }

    public function test_apply_posts_correction_to_match_count(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $count = $this->stocktake->open($this->warehouse);
        $this->stocktake->recordCount($count->lines->firstWhere('stock_state', StockState::Physical), '8');

        $this->stocktake->applyDifferences($count);

        $this->assertSame('8.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
        $this->assertSame(StockCountStatus::Completed, $count->fresh()->status);
    }

    public function test_movements_after_count_continue_separately(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $count = $this->stocktake->open($this->warehouse); // friert Soll = 10 ein
        $this->ledger->receipt($this->variant, $this->warehouse, '5'); // nach Zählzeitpunkt → physisch 15

        $this->stocktake->recordCount($count->lines->firstWhere('stock_state', StockState::Physical), '8');
        $this->stocktake->applyDifferences($count); // Differenz −2 gegen eingefrorenes Soll

        $this->assertSame('13.0000', $this->ledger->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    public function test_zero_difference_posts_no_correction(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '10');
        $count = $this->stocktake->open($this->warehouse);
        $this->stocktake->recordCount($count->lines->firstWhere('stock_state', StockState::Physical), '10');

        $this->stocktake->applyDifferences($count);
        $this->assertSame(1, StockMovement::query()->count(), 'keine Korrekturbuchung bei Differenz 0');
    }

    public function test_stock_counts_are_isolated_per_organization(): void {
        $this->ledger->receipt($this->variant, $this->warehouse, '1');
        $this->stocktake->open($this->warehouse);
        $this->assertSame(1, StockCount::query()->count());

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, StockCount::query()->count());
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
