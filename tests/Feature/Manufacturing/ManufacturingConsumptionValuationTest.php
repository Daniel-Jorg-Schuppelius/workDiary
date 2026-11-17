<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingConsumptionValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Enums\Inventory\{OwnershipType, ReservationStatus, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockMovement, StockValuation, StockValuationLayer, Warehouse, WarehouseBin};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Procedure\{ProcedureMaterialRequirement, ProcedureTemplateVersion};
use App\Services\Inventory\{FifoValuationService, InventoryLedger, ReservationService, ValuationService};
use App\Services\Manufacturing\{ManufacturingInventoryService, ManufacturingOrderService};
use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1710: Fertigungsverbrauch und Reservierungs-Erfüllung buchen
 * Eigenbestand bewertet nach dem Verfahren der Variante — FIFO verbraucht
 * Schichten, der Durchschnitt mindert sein Buch — mit dem Auftrag als Beleg.
 * Die Ist-Kosten der Materialposition kommen aus dem Kostensnapshot des
 * Abgangs. Fremdbestand bleibt unbewertet; Lagerplatz und Negativ-Freigabe
 * bleiben wie bisher.
 */
final class ManufacturingConsumptionValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ManufacturingInventoryService $link;

    private Warehouse $warehouse;

    private ArticleVariant $materialVariant;

    private Article $product;

    private ArticleVariant $productVariant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->link = app(ManufacturingInventoryService::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $material = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->materialVariant = $this->variantFor($material);
        $version = ProcedureTemplateVersion::factory()->create();
        ProcedureMaterialRequirement::factory()->perUnit('2')->create([
            'procedure_template_version_id' => $version->id,
            'article_id' => $material->id,
        ]);
        $this->product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_procedure_template_version_id' => $version->id,
        ]);
        $this->productVariant = $this->variantFor($this->product);
    }

    private function variantFor(Article $article): ArticleVariant {
        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $article->id,
        ]);
    }

    private function useFifo(): void {
        app(SettingsRegistry::class)->setServiceManaged('inventory.valuation_method', 'fifo', $this->organization);
        $this->organization->refresh();
    }

    /** Freigabe reserviert den Bedarf 2 × Zielmenge, soweit Bestand da ist. */
    private function releasedOrder(string $targetQty): ManufacturingOrder {
        $orders = app(ManufacturingOrderService::class);
        $order = $orders->createDraft($this->organization, $this->product, $this->productVariant, $targetQty, 'Stk', ['warehouse_id' => $this->warehouse->id]);

        return $orders->release($order);
    }

    /** @return \Illuminate\Support\Collection<int, StockMovement> */
    private function issues(): \Illuminate\Support\Collection {
        return StockMovement::query()->where('article_variant_id', $this->materialVariant->id)->where('movement_type', StockMovementType::Issue->value)->orderBy('id')->get();
    }

    private function remainingLayers(): string {
        return bcadd((string) StockValuationLayer::query()->where('article_variant_id', $this->materialVariant->id)->sum('qty_remaining'), '0', 4);
    }

    public function test_consumption_via_reservation_uses_the_fifo_layers_and_their_cost(): void {
        $this->useFifo();
        $fifo = app(FifoValuationService::class);
        $fifo->receipt($this->materialVariant, $this->warehouse, '5', '2.00');
        $fifo->receipt($this->materialVariant, $this->warehouse, '15', '4.00');
        $order = $this->releasedOrder('10');
        $material = $order->materials()->firstOrFail();
        $this->assertNotNull($material->stock_reservation_id);

        $this->link->consume($material, '7');

        $issue = $this->issues()->sole();
        $this->assertSame(['-7.0000', '18.0000'], [(string) $issue->qty_base, $issue->cost_total?->getAmount()], '5 × 2,00 + 2 × 4,00');
        $this->assertSame([$order->getMorphClass(), (int) $order->id], [$issue->source_type, (int) $issue->source_id]);
        $this->assertSame('13.0000', $this->remainingLayers());
        // Vorher: Stückkosten der nächsten Schicht × Menge = 14,00.
        $this->assertSame('18.0000', $material->refresh()->actual_cost?->getAmount());
        $this->assertSame('13.0000', app(InventoryLedger::class)->balance($this->materialVariant, $this->warehouse, StockState::Reserved));
    }

    public function test_consumption_without_reservation_is_valued_and_keeps_the_negative_approval(): void {
        $order = $this->releasedOrder('10'); // ohne Bestand: keine Reservierung
        $material = $order->materials()->firstOrFail();
        $this->assertNull($material->stock_reservation_id);
        app(ValuationService::class)->receipt($this->materialVariant, $this->warehouse, '4', '3.00');

        $this->link->consume($material, '3');

        $this->assertSame([['-3.0000', '9.0000']], $this->issues()->map(fn (StockMovement $m): array => [(string) $m->qty_base, $m->cost_total?->getAmount()])->all());
        $book = StockValuation::query()->where('article_variant_id', $this->materialVariant->id)->sole();
        $this->assertSame(['1.0000', '3.0000'], [bcadd((string) $book->qty_on_hand, '0', 4), $book->avg_cost?->getAmount()]);
        $this->assertSame('9.0000', $material->refresh()->actual_cost?->getAmount());

        // Gegenprobe: ohne Freigabe kein Negativbestand, mit Freigabe bewertet zum Durchschnitt.
        try {
            $this->link->consume($material, '2');
            $this->fail('Negativ-Entnahme ohne Freigabe wurde nicht blockiert.');
        } catch (RuntimeException) {
        }
        $this->link->consume($material->refresh(), '2', allowNegative: true);
        $this->assertSame('6.0000', $this->issues()->last()?->cost_total?->getAmount());
        $this->assertSame('-1.0000', bcadd((string) $book->refresh()->qty_on_hand, '0', 4));
        $this->assertSame('15.0000', $material->refresh()->actual_cost?->getAmount());
    }

    public function test_fulfilling_a_reservation_values_own_stock_on_its_bin_and_leaves_foreign_stock_unvalued(): void {
        $reservations = app(ReservationService::class);
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'F1']);
        app(ValuationService::class)->receipt($this->materialVariant, $this->warehouse, '10', '2.50', bin: $bin);
        $reservation = $reservations->reserve($this->materialVariant, $this->warehouse, '4', bin: $bin);

        $issue = $reservations->fulfill($reservation, '4');

        $this->assertSame('10.0000', $issue->costTotal());
        $this->assertSame((int) $bin->id, (int) $issue->first()->bin_id);
        $this->assertSame(ReservationStatus::Fulfilled, $reservation->fresh()?->status);
        $this->assertSame('6.0000', bcadd((string) StockValuation::query()->where('article_variant_id', $this->materialVariant->id)->sole()->qty_on_hand, '0', 4));

        // Fremdbestand: die Bewertung führt ihn nicht.
        app(InventoryLedger::class)->receipt($this->materialVariant, $this->warehouse, '5', OwnershipType::Consignment);
        $foreign = $reservations->reserve($this->materialVariant, $this->warehouse, '2', OwnershipType::Consignment);
        $consignment = $reservations->fulfill($foreign, '2')->first();
        $this->assertSame([OwnershipType::Consignment, null], [$consignment->ownership_type, $consignment->cost_total]);
        $this->assertSame('6.0000', bcadd((string) StockValuation::query()->where('article_variant_id', $this->materialVariant->id)->sole()->qty_on_hand, '0', 4));
    }
}
