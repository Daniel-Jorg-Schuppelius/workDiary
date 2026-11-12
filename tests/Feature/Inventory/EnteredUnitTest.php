<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnteredUnitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\ScanAction;
use App\Models\Article\{Article, ArticleUnit, ArticleVariant};
use App\Models\Inventory\{StockMovement, Warehouse};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Article\UnitConverter;
use App\Services\Inventory\{EnteredQuantity, InventoryLedger, ScanActionService, ValuationService};
use App\Services\Procurement\{GoodsReceiptService, PurchaseOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1584: Erfassung in einer gepflegten Einheit des Artikels — gebucht wird in
 * Basiseinheit, Eingabe und Einheit bleiben am Lagerjournal.
 */
final class EnteredUnitTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;
    private Article $article;
    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'm']);
        ArticleUnit::factory()->create(['article_id' => $this->article->id, 'code' => 'Rolle', 'factor_to_base' => '100', 'active' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default', 'sku' => 'KABEL-1',
        ]);
    }

    private function lastMovement(): StockMovement {
        return StockMovement::query()->orderByDesc('id')->firstOrFail();
    }

    public function test_manual_receipt_and_issue_convert_to_base_and_keep_the_entry(): void {
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid, 'variant' => $this->variant->sqid, 'movement' => 'receipt',
            'qty' => '2', 'unit' => 'Rolle', 'ownership' => 'own',
        ])->assertSessionHas('success');

        $this->assertSame('200.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
        $receipt = $this->lastMovement();
        $this->assertSame('200.0000', $receipt->qty_base);
        $this->assertSame('2.0000', $receipt->original_qty?->getNumericValue());
        $this->assertSame('Rolle', $receipt->original_unit);

        $this->actingAs($this->admin)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid, 'variant' => $this->variant->sqid, 'movement' => 'issue',
            'qty' => '0.5', 'unit' => 'Rolle', 'ownership' => 'own',
        ])->assertSessionHas('success');

        $issue = $this->lastMovement();
        $this->assertSame('-50.0000', $issue->qty_base);
        $this->assertSame('0.5000', $issue->original_qty?->getNumericValue());
        $this->assertSame('150.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));

        // Ohne Einheit bleibt es bei der Basiseinheit, die Eingabe steht trotzdem am Journal.
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid, 'variant' => $this->variant->sqid, 'movement' => 'receipt',
            'qty' => '3', 'ownership' => 'own',
        ])->assertSessionHas('success');
        $this->assertSame('m', $this->lastMovement()->original_unit);
        $this->assertSame('153.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));

        $this->actingAs($this->admin)->get(route('inventory.journal'))
            ->assertOk()->assertSee('Rolle')->assertSee(__('inventory.journal.col.entered'));
    }

    public function test_unknown_unit_is_rejected_without_booking(): void {
        $this->actingAs($this->admin)->post(route('inventory.movements.store'), [
            'warehouse' => $this->warehouse->sqid, 'variant' => $this->variant->sqid, 'movement' => 'receipt',
            'qty' => '2', 'unit' => 'Palette', 'ownership' => 'own',
        ])->assertSessionHas('error');

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_scan_books_in_entered_unit(): void {
        app(ScanActionService::class)->book('KABEL-1', ScanAction::Receipt, $this->warehouse, '1', ['unit' => 'Rolle']);

        $this->assertSame('100.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
        $this->assertSame('Rolle', $this->lastMovement()->original_unit);

        $this->actingAs($this->admin)->post(route('inventory.scan.book'), [
            'code' => 'KABEL-1', 'action' => 'issue', 'warehouse' => $this->warehouse->sqid, 'qty' => '0.25', 'unit' => 'Rolle',
        ])->assertSessionHas('success');
        $this->assertSame('75.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
    }

    public function test_goods_receipt_converts_line_unit_and_values_per_base_unit(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, Supplier::factory()->create(['organization_id' => $this->organization->id]), $this->warehouse);
        $line = $orders->addLine($po, $this->article, '3', ['variant' => $this->variant, 'unit' => 'Rolle', 'unit_price' => '80']);
        $orders->submit($po);

        app(GoodsReceiptService::class)->receive($line, '2');

        // Zeile zählt in Rollen, das Lager in Metern; 80 je Rolle = 0,80 je Meter.
        $this->assertSame('2.0000', $line->fresh()->received_qty?->getNumericValue());
        $this->assertSame('200.0000', app(InventoryLedger::class)->available($this->variant, $this->warehouse));
        $this->assertSame('0.8000', app(ValuationService::class)->unitCost($this->variant, $this->warehouse));
        $movement = $this->lastMovement();
        $this->assertSame('2.0000', $movement->original_qty?->getNumericValue());
        $this->assertSame('Rolle', $movement->original_unit);
        $this->assertSame('160.0000', $movement->cost_total?->getAmount());
    }

    public function test_purchase_order_line_accepts_only_maintained_units(): void {
        $po = app(PurchaseOrderService::class)->createDraft($this->organization, Supplier::factory()->create(['organization_id' => $this->organization->id]), $this->warehouse);
        $this->article->update(['purchasable' => true]);

        $this->actingAs($this->admin)->post(route('purchase-orders.lines.add', $po), [
            'article' => $this->article->sqid, 'qty' => '1', 'unit' => 'Palette',
        ])->assertSessionHasErrors('unit');

        $this->actingAs($this->admin)->post(route('purchase-orders.lines.add', $po), [
            'article' => $this->article->sqid, 'qty' => '1', 'unit' => 'Rolle', 'unit_price' => '80',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Rolle', $po->lines()->sole()->unit);
    }

    public function test_converter_rounds_half_up_and_rejects_quantities_that_vanish(): void {
        ArticleUnit::factory()->create(['article_id' => $this->article->id, 'code' => 'mm', 'factor_to_base' => '0.001', 'active' => true]);
        $converter = app(UnitConverter::class);

        $this->assertSame('0.0015', $converter->toBase($this->article, '1.5', 'mm'));
        $this->assertSame('0.0002', $converter->toBase($this->article, '0.15', 'mm')); // 0,00015 → kaufmännisch

        $this->expectException(RuntimeException::class);
        EnteredQuantity::of($this->variant, '0.04', 'mm');
    }

    public function test_stocktake_counts_in_article_units(): void {
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '250');
        $stocktake = app(\App\Services\Inventory\StocktakeService::class);
        $count = $stocktake->open($this->warehouse, $this->admin->id);
        $line = $count->lines()->sole();

        // Zeilenerfassung mit Einheit je Zeile.
        $this->actingAs($this->admin)->post(route('inventory.counts.record', $count), [
            'counted' => [$line->id => '2'], 'unit' => [$line->id => 'Rolle'],
        ])->assertSessionHas('success');
        $this->assertSame('200.0000', $line->fresh()->counted_qty);

        // Scan setzt, mobile Zählung addiert — beide in der gewählten Einheit.
        $this->actingAs($this->admin)->post(route('inventory.counts.scan', $count), ['code' => 'KABEL-1', 'qty' => '1.5', 'unit' => 'Rolle'])
            ->assertSessionHas('success');
        $this->assertSame('150.0000', $line->fresh()->counted_qty);
        $this->actingAs($this->admin)->post(route('inventory.counts.scan-add', $count), ['code' => 'KABEL-1', 'qty' => '1', 'unit' => 'Rolle']);
        $this->actingAs($this->admin)->post(route('inventory.counts.scan-add', $count), ['code' => 'KABEL-1', 'qty' => '25']);
        $this->assertSame('275.0000', $line->fresh()->counted_qty);

        $this->actingAs($this->admin)->post(route('inventory.counts.scan', $count), ['code' => 'KABEL-1', 'qty' => '1', 'unit' => 'Palette'])
            ->assertSessionHas('error', __('inventory.error.unit_not_maintained', ['unit' => 'Palette', 'article' => $this->article->name]));

        $this->actingAs($this->admin)->get(route('inventory.counts.show', $count))->assertOk()->assertSee('Rolle');
        $this->actingAs($this->admin)->get(route('inventory.counts.mobile', $count))->assertOk()->assertSee('Rolle');
    }

    public function test_purchase_suggestion_orders_in_the_pack_unit_of_the_supplier_article(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        \App\Models\Article\ArticleSupply::query()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id, 'supplier_id' => $supplier->id,
            'supplier_sku' => 'L-KABEL', 'moq' => '1', 'pack_size' => '100', 'lead_time_days' => 3,
            'purchase_price' => '0.80', 'currency' => 'EUR', 'is_preferred' => true,
        ]);
        app(\App\Services\Inventory\StockLevelService::class)->setLevels($this->variant, $this->warehouse, '50', '150');

        $orders = app(\App\Services\Procurement\ProcurementSuggestionService::class)->createOrders($this->warehouse, $this->organization, $this->admin->id);

        $line = $orders[0]->lines()->sole();
        // Bedarf 150 m → auf die Packung gerundet 200 m = 2 Rollen zu je 80.
        $this->assertSame('Rolle', $line->unit);
        $this->assertSame('2.0000', $line->ordered_qty?->getNumericValue());
        $this->assertSame('80.0000', $line->unit_price?->getAmount());
    }
}
