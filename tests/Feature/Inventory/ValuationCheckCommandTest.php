<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValuationCheckCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Audit\AuditLog;
use App\Models\Inventory\{StockMovement, StockValuation, StockValuationLayer, Warehouse};
use App\Services\Inventory\{FifoValuationService, InventoryLedger, InventoryValuationManager, ValuationService};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1736: `inventory:valuation-check` meldet je Variante und Lager die
 * Abweichung zwischen physischem Eigenbestand und Bewertungsbuch. `--align`
 * trägt eine fehlende Menge ohne Lagerbewegung zur Preisfolge des manuellen
 * Eingangs nach (geschätzt, im Audit des Lagers), einmal; ein Buch über dem
 * Bestand wird nur gemeldet.
 */
final class ValuationCheckCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Article $article;

    private ArticleVariant $variant;

    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'currency' => 'EUR']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'sku' => 'SCHRAUBE-6',
            'currency' => 'EUR',
        ]);
    }

    /** @param array<string, mixed> $options */
    private function check(array $options = []): string {
        $this->assertSame(0, Artisan::call('inventory:valuation-check', ['--organization' => (string) $this->organization->id, ...$options]));

        return Artisan::output();
    }

    private function book(): ?StockValuation {
        return StockValuation::query()->where('article_variant_id', $this->variant->id)->where('warehouse_id', $this->warehouse->id)->first();
    }

    /** @return numeric-string */
    private function stockValue(): string {
        $summary = app(InventoryValuationManager::class)->summariesForWarehouse($this->warehouse, $this->organization)[$this->variant->id] ?? null;

        return $summary['value'] ?? '0';
    }

    private function alignments(): int {
        return AuditLog::query()->where('event', 'inventory.valuationAligned')->count();
    }

    public function test_an_average_gap_is_reported_and_aligned_once_at_the_estimated_cost(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '6', '5.00');
        // Stand vor MVP-1709: der manuelle Eingang ging ohne Wert ins Lagerbuch.
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '4');
        $this->variant->forceFill(['purchase_price' => '2.00'])->save();
        $journal = StockMovement::query()->count();

        $report = $this->check();
        $this->assertStringContainsString('SCHRAUBE-6', $report);
        $this->assertStringContainsString('fehlt im Buch', $report);
        $this->assertStringContainsString('geschätzt', $report);
        $this->assertSame(['6.0000', '5.0000', '30.0000'], [bcadd($this->book()->qty_on_hand ?? '0', '0', 4), $this->book()?->avg_cost?->getAmount(), $this->stockValue()]);

        $aligned = $this->check(['--align' => true]);
        $this->assertStringContainsString('nachgetragen: 4.0000 (geschätzt)', $aligned);
        $this->assertStringNotContainsString('38.0000', $aligned, 'Die Ausgabe nennt Mengen, keinen Wert.');
        // (6 × 5 + 4 × 2) / 10
        $this->assertSame(['10.0000', '3.8000', '38.0000'], [bcadd($this->book()->qty_on_hand ?? '0', '0', 4), $this->book()?->avg_cost?->getAmount(), $this->stockValue()]);
        $this->assertSame($journal, StockMovement::query()->count(), 'Die Menge ist schon da — keine Lagerbewegung.');

        $audit = AuditLog::query()->where('event', 'inventory.valuationAligned')->sole();
        $this->assertSame([MorphMap::stableKey(Warehouse::class), $this->warehouse->id], [$audit->auditable_type, (int) $audit->auditable_id]);
        $this->assertSame(
            ['article_variant_id' => $this->variant->id, 'method' => 'moving_average', 'qty' => '4.0000', 'unit_cost' => '2.0000', 'currency' => 'EUR', 'estimated' => true],
            $audit->changes,
        );

        // Wiederholung: keine Lücke mehr, kein zweiter Nachtrag.
        $this->assertStringContainsString('stimmen überein', $this->check(['--align' => true]));
        $this->assertSame(1, $this->alignments());
        $this->assertSame('10.0000', bcadd($this->book()->qty_on_hand ?? '0', '0', 4));
    }

    public function test_a_fifo_gap_becomes_the_oldest_layer_without_a_movement(): void {
        $this->article->update(['valuation_method' => 'fifo']);
        Carbon::setTestNow('2026-03-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '3', '4.00');
        Carbon::setTestNow('2026-04-01 08:00:00');
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '2');
        Carbon::setTestNow();
        $this->variant->forceFill(['purchase_price' => '1.00'])->save();
        $this->assertSame('12.0000', $this->stockValue());

        $this->check(['--align' => true]);

        $layer = StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->whereNull('source_movement_id')->sole();
        $this->assertSame(['2.0000', '1.0000'], [bcadd((string) $layer->qty_remaining, '0', 4), $layer->unit_cost?->getAmount()]);
        $this->assertTrue($layer->acquired_at->lessThan(Carbon::parse('2026-03-01 08:00:00')));
        $this->assertSame('14.0000', $this->stockValue());
        $this->assertNull($this->book(), 'Das Durchschnittsbuch bleibt bei FIFO still.');

        // Die nachgetragene Menge geht als älteste zuerst ab.
        $issue = app(FifoValuationService::class)->issue($this->variant, $this->warehouse, '2');
        $this->assertSame('2.0000', $issue->costTotal());
        $this->assertSame('12.0000', $this->stockValue());
    }

    public function test_a_book_above_the_stock_is_only_reported(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '5', '3.00');
        // Stand vor MVP-1692: die Entnahme ging ohne Wert ab, das Buch blieb stehen.
        app(InventoryLedger::class)->issue($this->variant, $this->warehouse, '2');

        $report = $this->check(['--align' => true]);

        $this->assertStringContainsString('Buch über Bestand — nur Meldung', $report);
        $this->assertSame('5.0000', bcadd($this->book()->qty_on_hand ?? '0', '0', 4));
        $this->assertSame(0, $this->alignments());
    }

    public function test_dry_run_books_nothing(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '1', '3.00');
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '2');

        $report = $this->check(['--align' => true, '--dry-run' => true]);

        $this->assertStringContainsString('würde geschätzt nachgetragen', $report);
        $this->assertStringContainsString('Probelauf', $report);
        $this->assertSame('1.0000', bcadd($this->book()->qty_on_hand ?? '0', '0', 4));
        $this->assertSame(0, $this->alignments());
    }

    public function test_a_matching_book_reports_no_deviation(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '4', '3.00');
        app(ValuationService::class)->issue($this->variant, $this->warehouse, '1');

        $this->assertStringContainsString('stimmen überein', $this->check());
    }
}
