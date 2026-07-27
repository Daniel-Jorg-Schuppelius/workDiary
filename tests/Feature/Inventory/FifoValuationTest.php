<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FifoValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\ValuationMethod;
use App\Models\{Article, ArticleVariant, Warehouse};
use App\Services\Inventory\{FifoValuationService, InventoryValuationManager, ValuationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * FIFO-Bewertung (Feature 048, E3): Zugangsschichten, Verbrauch ältester zuerst
 * mit exaktem Kostensnapshot, Gesamtwert und Verfahrensauswahl je Organisation.
 */
final class FifoValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private FifoValuationService $fifo;
    private ArticleVariant $variant;
    private Article $article;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->fifo = app(FifoValuationService::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_receipt_creates_layer_and_values_stock(): void {
        $this->fifo->receipt($this->variant, $this->warehouse, '10', '2.00');

        $this->assertSame('10.0000', $this->fifo->onHand($this->variant, $this->warehouse));
        $this->assertSame('20.0000', $this->fifo->totalValue($this->variant, $this->warehouse));
    }

    public function test_issue_consumes_oldest_layer_first(): void {
        $this->fifo->receipt($this->variant, $this->warehouse, '10', '2'); // älteste Schicht
        $this->fifo->receipt($this->variant, $this->warehouse, '10', '3');

        $this->assertSame('2.0000', $this->fifo->unitCost($this->variant, $this->warehouse)); // nächste Schicht

        $movement = $this->fifo->issue($this->variant, $this->warehouse, '15');

        // 10 × 2 + 5 × 3 = 35
        $this->assertSame('35.0000', $movement->cost_total?->getAmount());
        $this->assertSame('5.0000', $this->fifo->onHand($this->variant, $this->warehouse));
        $this->assertSame('15.0000', $this->fifo->totalValue($this->variant, $this->warehouse));
    }

    public function test_manager_resolves_method_per_article(): void {
        $this->article->update(['valuation_method' => 'fifo']);

        $strategy = app(InventoryValuationManager::class)->forVariant($this->variant->fresh(), $this->organization);

        $this->assertInstanceOf(FifoValuationService::class, $strategy);
    }

    public function test_manager_resolves_method_per_organization(): void {
        $manager = app(InventoryValuationManager::class);

        $this->assertInstanceOf(ValuationService::class, $manager->for($this->organization));

        $this->organization->update(['settings' => ['valuation_method' => 'fifo']]);
        $strategy = $manager->for($this->organization->fresh());

        $this->assertInstanceOf(FifoValuationService::class, $strategy);
        $this->assertSame(ValuationMethod::Fifo, $strategy->method());
    }
}
