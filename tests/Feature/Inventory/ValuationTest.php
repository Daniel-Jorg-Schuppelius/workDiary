<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Models\{Article, ArticleVariant, Organization, StockValuation, Warehouse};
use App\Services\Inventory\ValuationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Bestandsbewertung mit gleitendem Durchschnitt (Feature 048, MVP-070):
 * Durchschnittsfortschreibung bei Zugängen, Abgangsbewertung zum Durchschnitt,
 * unveränderlicher Kostensnapshot je Bewegung und Mandantengrenze.
 */
final class ValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ValuationService $valuation;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->valuation = app(ValuationService::class);
        $this->variant = $this->makeVariant();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_moving_average_after_receipts(): void {
        $this->valuation->receipt($this->variant, $this->warehouse, '10', '2');
        $this->valuation->receipt($this->variant, $this->warehouse, '10', '4');

        $this->assertSame('3.0000', $this->valuation->average($this->variant, $this->warehouse));
        $this->assertSame('60.0000', $this->valuation->totalValue($this->variant, $this->warehouse));
    }

    public function test_issue_valued_at_average_keeps_average(): void {
        $this->valuation->receipt($this->variant, $this->warehouse, '10', '2');
        $this->valuation->receipt($this->variant, $this->warehouse, '10', '4'); // avg 3

        $movement = $this->valuation->issue($this->variant, $this->warehouse, '5');

        $this->assertSame('15.0000', $movement->cost_total); // 5 × 3
        $this->assertSame('3.0000', $this->valuation->average($this->variant, $this->warehouse));
        $this->assertSame('45.0000', $this->valuation->totalValue($this->variant, $this->warehouse));
    }

    public function test_receipt_stamps_cost_snapshot_on_movement(): void {
        $movement = $this->valuation->receipt($this->variant, $this->warehouse, '10', '2.5');

        $this->assertSame('2.5000', $movement->cost_unit);
        $this->assertSame('25.0000', $movement->cost_total);
    }

    public function test_historical_cost_snapshot_stays_unchanged_after_price_change(): void {
        $this->valuation->receipt($this->variant, $this->warehouse, '10', '2');
        $issue = $this->valuation->issue($this->variant, $this->warehouse, '4'); // bewertet zu 2 → 8

        // Späterer teurerer Zugang ändert den Durchschnitt …
        $this->valuation->receipt($this->variant, $this->warehouse, '10', '5');
        $this->assertNotSame('2.0000', $this->valuation->average($this->variant, $this->warehouse));

        // … aber der Kostensnapshot der alten Abgangsbewegung bleibt unverändert.
        $this->assertSame('8.0000', $issue->fresh()->cost_total);
    }

    public function test_issue_blocks_negative_stock(): void {
        $this->valuation->receipt($this->variant, $this->warehouse, '2', '1');

        $this->expectException(RuntimeException::class);
        $this->valuation->issue($this->variant, $this->warehouse, '5');
    }

    public function test_valuation_is_isolated_per_organization(): void {
        $this->valuation->receipt($this->variant, $this->warehouse, '5', '3');
        $this->assertSame(1, StockValuation::query()->count());

        $orgB = Organization::factory()->create();
        app()->instance('currentOrganization', $orgB);
        $this->assertSame(0, StockValuation::query()->count());
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
