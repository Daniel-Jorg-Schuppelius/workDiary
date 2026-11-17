<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManualReceiptValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{OwnershipType, ScanAction};
use App\Models\Article\{Article, ArticleUnit, ArticleVariant};
use App\Models\Inventory\{StockMovement, StockValuation, StockValuationLayer, Warehouse, WarehouseBin};
use App\Models\Platform\User;
use App\Services\Inventory\{FifoValuationService, ScanActionService, ValuationService};
use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1709: Der manuelle Eingang (Formular und Scan) bucht Eigenbestand
 * bewertet über die Strategie der Variante — mit dem erfassten Einstandspreis
 * je Einheit, leer mit dem Einkaufspreis der Variante, ohne ihn mit dem
 * aktuellen Durchschnitt. FIFO legt eine Schicht an, der Durchschnitt rechnet
 * neu; Fremdbestand bleibt unbewertet.
 */
final class ManualReceiptValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $teamlead;

    private Warehouse $warehouse;

    private Article $article;

    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'currency' => 'EUR']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'sku' => 'KLEMME-1',
            'currency' => 'EUR',
        ]);
    }

    private function useFifo(): void {
        app(SettingsRegistry::class)->setServiceManaged('inventory.valuation_method', 'fifo', $this->organization);
        $this->organization->refresh();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function receive(array $overrides = []): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->teamlead)->post(route('inventory.movements.store'), array_merge([
            'warehouse' => $this->warehouse->sqid,
            'variant' => $this->variant->sqid,
            'movement' => 'receipt',
            'qty' => '10',
            'ownership' => OwnershipType::Own->value,
        ], $overrides));
    }

    private function lastReceipt(): StockMovement {
        return StockMovement::query()->where('article_variant_id', $this->variant->id)->where('movement_type', 'receipt')->latest('id')->firstOrFail();
    }

    private function book(): StockValuation {
        return StockValuation::query()->where('article_variant_id', $this->variant->id)->where('warehouse_id', $this->warehouse->id)->sole();
    }

    public function test_entered_unit_cost_values_the_receipt_and_moves_the_average(): void {
        $this->receive(['unit_cost' => '2,50'])->assertSessionHas('success', __('inventory.flash.movement_posted'));

        $movement = $this->lastReceipt();
        $this->assertSame(['10.0000', '2.5000', '25.0000', 'EUR'], [(string) $movement->qty_base, $movement->cost_unit?->getAmount(), $movement->cost_total?->getAmount(), $movement->currency?->value]);
        $this->assertSame(['10.0000', '2.5000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);

        // Englische Schreibweise; der Durchschnitt rechnet neu: (25 + 45) / 20.
        $this->receive(['unit_cost' => '4.50'])->assertSessionHasNoErrors();
        $this->assertSame(['20.0000', '3.5000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
    }

    public function test_empty_unit_cost_takes_the_purchase_price_of_the_variant_then_of_the_article(): void {
        $this->variant->forceFill(['purchase_price' => '3.20'])->save();
        $this->receive()->assertSessionHasNoErrors();
        $movement = $this->lastReceipt();
        $this->assertSame('3.2000', $movement->cost_unit?->getAmount());

        // Ohne eigenen Preis erbt die Variante den des Artikels.
        $this->variant->forceFill(['purchase_price' => null])->save();
        $this->article->forceFill(['default_purchase_price' => '2.00'])->save();
        $this->receive()->assertSessionHasNoErrors();
        $movement = $this->lastReceipt();
        $this->assertSame('2.0000', $movement->cost_unit?->getAmount());
    }

    public function test_without_purchase_price_the_current_average_values_the_receipt(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '4', '1.75');

        $this->receive(['qty' => '6'])->assertSessionHasNoErrors();

        $movement = $this->lastReceipt();
        $this->assertSame(['1.7500', '10.5000'], [$movement->cost_unit?->getAmount(), $movement->cost_total?->getAmount()]);
        $this->assertSame(['10.0000', '1.7500'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);

        // Gegenprobe: ein Einkaufspreis in fremder Währung passt nicht ins Buch und bleibt außen vor.
        $this->variant->forceFill(['currency' => 'USD', 'purchase_price' => '9.99'])->save();
        $this->receive(['qty' => '1'])->assertSessionHasNoErrors();
        $movement = $this->lastReceipt();
        $this->assertSame(['1.7500', 'EUR'], [$movement->cost_unit?->getAmount(), $movement->currency?->value]);
    }

    public function test_without_any_price_or_book_the_receipt_is_valued_at_zero(): void {
        $this->receive(['qty' => '3'])->assertSessionHasNoErrors();

        $movement = $this->lastReceipt();
        $this->assertSame(['0.0000', '0.0000'], [$movement->cost_unit?->getAmount(), $movement->cost_total?->getAmount()]);
        $this->assertSame('3.0000', bcadd((string) $this->book()->qty_on_hand, '0', 4), 'Auch ohne Preis wächst das Buch mit dem Bestand.');
    }

    public function test_fifo_receipt_adds_a_layer_and_the_price_is_per_entered_unit(): void {
        $this->useFifo();
        ArticleUnit::factory()->create(['article_id' => $this->article->id, 'code' => 'Karton', 'factor_to_base' => '12', 'active' => true]);

        $this->receive(['qty' => '2', 'unit' => 'Karton', 'unit_cost' => '24,00'])->assertSessionHasNoErrors();

        $layer = StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->sole();
        $movement = $this->lastReceipt();
        $this->assertSame(['24.0000', '2.0000', (int) $movement->id], [bcadd((string) $layer->qty_remaining, '0', 4), $layer->unit_cost?->getAmount(), (int) $layer->source_movement_id]);
        $this->assertSame(['24.0000', '2.0000', 'Karton'], [(string) $movement->qty_base, $movement->original_qty?->getNumericValue(), $movement->original_unit]);
        $this->assertFalse(StockValuation::query()->where('article_variant_id', $this->variant->id)->exists(), 'Das Durchschnittsbuch bleibt bei FIFO still.');

        // Ohne Preis und Einkaufspreis: Durchschnitt der Restschichten ((24 × 2 + 8 × 5) / 32 = 2,75).
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '8', '5.00');
        $this->receive(['qty' => '4'])->assertSessionHasNoErrors();
        $movement = $this->lastReceipt();
        $this->assertSame('2.7500', $movement->cost_unit?->getAmount());
        $this->assertSame(3, StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->count());
    }

    public function test_foreign_stock_stays_unvalued_and_the_bin_is_kept(): void {
        $this->receive(['ownership' => OwnershipType::Consignment->value, 'unit_cost' => '7,00'])->assertSessionHasNoErrors();

        $foreign = $this->lastReceipt();
        $this->assertSame([OwnershipType::Consignment, null, null], [$foreign->ownership_type, $foreign->cost_unit, $foreign->cost_total]);
        $this->assertFalse(StockValuation::query()->where('article_variant_id', $this->variant->id)->exists());

        // Eigenbestand auf einem Platz: bewertet, der Platz bleibt an der Bewegung.
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R2']);
        $this->receive(['qty' => '2', 'bin' => $bin->sqid, 'unit_cost' => '1,25'])->assertSessionHasNoErrors();
        $own = $this->lastReceipt();
        $this->assertSame([(int) $bin->id, '1.2500'], [(int) $own->bin_id, $own->cost_unit?->getAmount()]);
    }

    public function test_an_unreadable_or_negative_unit_cost_books_nothing(): void {
        $this->receive(['unit_cost' => 'zwölf'])->assertSessionHasErrors('unit_cost');
        $this->receive(['unit_cost' => '-1,00'])->assertSessionHasErrors('unit_cost');

        $this->assertSame(0, StockMovement::query()->where('article_variant_id', $this->variant->id)->count());
    }

    public function test_scan_receipt_is_valued_like_the_form(): void {
        $this->variant->forceFill(['purchase_price' => '0.80'])->save();

        $this->actingAs($this->teamlead)->post(route('inventory.scan.book'), [
            'code' => 'KLEMME-1',
            'action' => ScanAction::Receipt->value,
            'warehouse' => $this->warehouse->sqid,
            'qty' => '5',
            'unit_cost' => '0,95',
        ])->assertSessionHas('success', __('inventory.scan.booked'));
        $movement = $this->lastReceipt();
        $this->assertSame(['0.9500', '4.7500'], [$movement->cost_unit?->getAmount(), $movement->cost_total?->getAmount()]);

        // Gegenprobe ohne Preis: der Einkaufspreis der Variante.
        $movement = app(ScanActionService::class)->book('KLEMME-1', ScanAction::Receipt, $this->warehouse, '5');
        $this->assertInstanceOf(StockMovement::class, $movement);
        $this->assertSame('0.8000', $movement->cost_unit?->getAmount());
        $this->assertSame(['10.0000', '0.8750'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
    }
}
