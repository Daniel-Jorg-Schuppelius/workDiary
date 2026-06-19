<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CycleCountTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\StockCountType;
use App\Models\{Article, ArticleVariant, Warehouse};
use App\Services\Inventory\{CycleCountPlanner, StocktakeService, ValuationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Mobile & zyklische Inventur (Feature 048, E6): ABC-Klassifizierung nach
 * Bestandswert, zyklische Teilzählung und Scan-gestützte Erfassung.
 */
final class CycleCountTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Warehouse $warehouse;
    private ArticleVariant $a;
    private ArticleVariant $b;
    private ArticleVariant $c;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);

        $this->a = $this->variant($article, 'A', 'SKU-A');
        $this->b = $this->variant($article, 'B', 'SKU-B');
        $this->c = $this->variant($article, 'C', 'SKU-C');

        // Bestandswerte 80 / 15 / 5 → ABC.
        $valuation = app(ValuationService::class);
        $valuation->receipt($this->a, $this->warehouse, '80', '1');
        $valuation->receipt($this->b, $this->warehouse, '15', '1');
        $valuation->receipt($this->c, $this->warehouse, '5', '1');
    }

    public function test_abc_classification_by_value(): void {
        $classes = app(CycleCountPlanner::class)->classify($this->warehouse);

        $this->assertSame('A', $classes[$this->a->id]);
        $this->assertSame('B', $classes[$this->b->id]);
        $this->assertSame('C', $classes[$this->c->id]);
    }

    public function test_cycle_count_freezes_only_due_variants(): void {
        $planner = app(CycleCountPlanner::class);
        $due = $planner->dueVariants($this->warehouse, ['A']);
        $this->assertSame([$this->a->id], $due);

        $count = app(StocktakeService::class)->openCycle($this->warehouse, $due);

        $this->assertSame(StockCountType::Cycle, $count->count_type);
        $this->assertSame(1, $count->lines()->count());
        $this->assertSame($this->a->id, $count->lines()->firstOrFail()->article_variant_id);
    }

    public function test_scheduled_command_opens_cycle_counts(): void {
        $this->artisan('inventory:cycle-counts', ['--class' => 'A', '--org' => $this->organization->id])->assertExitCode(0);

        app()->instance('currentOrganization', $this->organization);
        $this->assertGreaterThan(0, \App\Models\StockCount::query()->where('count_type', 'cycle')->count());
    }

    public function test_record_by_scan_hits_matching_line(): void {
        $stocktake = app(StocktakeService::class);
        $count = $stocktake->openCycle($this->warehouse, [$this->a->id]);

        $line = $stocktake->recordByScan($count, 'SKU-A', '79');

        $this->assertSame('79.0000', $line->counted_qty);
        $this->assertSame($this->a->id, $line->article_variant_id);
    }

    private function variant(Article $article, string $sig, string $sku): ArticleVariant {
        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'option_signature' => $sig, 'sku' => $sku,
        ]);
    }
}
