<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotSplitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Models\{Article, ArticleVariant, StockLot, StockValuationLayer, Warehouse};
use App\Services\Inventory\{LotService, LotSplitService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Los-Split/-Merge (Feature 047/048, E7): Bestand zwischen Chargen verschieben,
 * Einzelkosten erhalten, Mengen konsistent.
 */
final class LotSplitTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LotService $lots;
    private LotSplitService $split;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->lots = app(LotService::class);
        $this->split = app(LotSplitService::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'batch_required' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_split_moves_quantity_keeping_cost(): void {
        $source = $this->lots->register($this->variant, 'L1');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $source);

        $target = $this->split->split($source, '4', 'L1-A');

        $this->assertSame('6.0000', $this->lots->onHand($source));
        $this->assertSame('4.0000', $this->lots->onHand($target));
        $this->assertSame('2.0000', StockValuationLayer::query()->where('stock_lot_id', $target->id)->value('unit_cost')?->getAmount());
    }

    public function test_split_beyond_stock_throws(): void {
        $source = $this->lots->register($this->variant, 'L1');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $source);

        $this->expectException(RuntimeException::class);
        $this->split->split($source, '5', 'L1-A');
    }

    public function test_merge_combines_lots(): void {
        $a = $this->lots->register($this->variant, 'A');
        $b = $this->lots->register($this->variant, 'B');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '6', '2', $a);
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '5', '3', $b);

        $this->split->merge($b, $a);

        $this->assertSame('11.0000', $this->lots->onHand($a));
        $this->assertSame('0.0000', $this->lots->onHand($b));
        $this->assertSame(StockLot::STATUS_MERGED, $b->fresh()->status);
    }
}
