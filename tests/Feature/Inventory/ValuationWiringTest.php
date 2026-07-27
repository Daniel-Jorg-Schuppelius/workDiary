<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValuationWiringTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Models\{Article, ArticleVariant, StockMovement, StockValuation, StockValuationLayer, Warehouse};
use App\Services\Inventory\{FifoValuationService, ValuationBackfillService};
use App\Services\Manufacturing\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Umstellung auf Schichtbewertung + Verdrahtung des Standard-Buchungspfads
 * (Feature 048, E3): Backfill aus dem Durchschnitt, Umstellungs-Command und
 * COGS-Bewertung der Auslieferung über das aktive Verfahren.
 */
final class ValuationWiringTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Article $article;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_backfill_seeds_layer_from_average_and_is_idempotent(): void {
        StockValuation::query()->create([
            'organization_id' => $this->organization->id,
            'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_on_hand' => '10', 'avg_cost' => '2', 'currency' => 'EUR',
        ]);

        $created = app(ValuationBackfillService::class)->backfill($this->organization);
        $this->assertSame(1, $created);

        $layer = StockValuationLayer::query()->firstOrFail();
        $this->assertSame('10.0000', $layer->qty_remaining);
        $this->assertSame('2.0000', $layer->unit_cost?->getAmount());

        $this->assertSame(0, app(ValuationBackfillService::class)->backfill($this->organization));
    }

    public function test_command_runs(): void {
        $this->artisan('inventory:init-valuation-layers', ['--org' => $this->organization->id])->assertExitCode(0);
    }

    public function test_delivery_values_cogs_via_active_method(): void {
        $this->article->update(['valuation_method' => 'fifo']);
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '10', '2');

        app(DeliveryService::class)->deliver($this->variant, $this->warehouse, '5');

        $issue = StockMovement::query()->where('movement_type', 'issue')->latest('id')->firstOrFail();
        $this->assertSame('10.0000', $issue->cost_total?->getAmount()); // 5 × 2
    }
}
