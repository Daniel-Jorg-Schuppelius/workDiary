<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RmaRestockValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Claims;

use App\Enums\Claims\ClaimRmaDisposition;
use App\Enums\Inventory\{SerialSource, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Claims\ClaimRmaReturn;
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, StockMovement, StockSerial, StockValuation, StockValuationLayer, Warehouse};
use App\Models\Platform\User;
use App\Services\Claims\{ClaimCaseService, ClaimRmaService, PortalReturnService};
use App\Services\Inventory\Claims\InventoryRmaStockHandler;
use App\Services\Inventory\{FifoValuationService, InventoryLedger, SerialService, ValuationService};
use App\Services\Manufacturing\DeliveryService;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1728: Die Wiedereinlagerung eines Rückläufers in den freien Bestand ist
 * bewertet — zum Stückwert des ursprünglichen Abgangs, wenn die Rücksendung
 * ihn über ihre Auslieferung (Portal, MVP-1737) oder die ihrer Seriennummer
 * kennt, sonst zum Durchschnitt des Lagers. Quarantäne-Eingang und Umbuchung aus der Quarantäne bleiben
 * wertneutral, eine Wiederholung bewertet nicht erneut.
 */
final class RmaRestockValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Article $article;

    private ArticleVariant $variant;

    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'currency' => 'EUR']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'sku' => 'VENTIL-15',
            'currency' => 'EUR',
        ]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function shipSerial(string $serialNo): StockSerial {
        $serial = app(SerialService::class)->register($this->variant, $serialNo, SerialSource::Purchased, $this->warehouse);
        app(DeliveryService::class)->deliver($this->variant, $this->warehouse, '1', customer: $this->customer, serialIds: [$serial->id]);

        return $serial->refresh();
    }

    private function returned(string $qty, ?StockSerial $serial = null): ClaimRmaReturn {
        $case = app(ClaimCaseService::class)->open($this->organization, $this->admin, [
            'title' => 'Ventil undicht',
            'source' => 'manual',
            'priority' => 'normal',
            'severity' => 'minor',
            'customer_id' => $this->customer->id,
        ]);
        $rmas = app(ClaimRmaService::class);
        $rma = $rmas->announce($case, [
            'article_id' => $this->article->id,
            'article_variant_id' => $this->variant->id,
            'stock_serial_id' => $serial?->id,
            'qty' => $qty,
        ]);

        return $rmas->receive($rma, $this->admin, ['warehouse_id' => $this->warehouse->id, 'stock_state' => StockState::Quality]);
    }

    private function restock(ClaimRmaReturn $rma): void {
        app(ClaimRmaService::class)->decideDisposition($rma->fresh() ?? $rma, $this->admin, ClaimRmaDisposition::Restock);
    }

    private function restockIn(ClaimRmaReturn $rma): StockMovement {
        return StockMovement::query()->where('idempotency_key', 'claim-rma:' . $rma->id . ':restock-in')->sole();
    }

    private function book(): StockValuation {
        return StockValuation::query()->where('article_variant_id', $this->variant->id)->where('warehouse_id', $this->warehouse->id)->sole();
    }

    public function test_restock_takes_the_unit_cost_of_the_original_delivery(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '3', '6.00');
        $serial = $this->shipSerial('SN-V15-1');
        $delivery = StockDelivery::query()->sole();
        $issue = StockMovement::query()->where('movement_type', StockMovementType::Issue->value)->sole();
        $this->assertSame([MorphMap::alias(StockDelivery::class), $delivery->id, '6.0000'], [$issue->source_type, (int) $issue->source_id, $issue->cost_unit?->getAmount()]);

        // Danach steigt der Durchschnitt: (2 × 6 + 2 × 12) / 4 = 9.
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '2', '12.00');
        $rma = $this->returned('1', $serial);
        $this->restock($rma);

        $this->assertSame(['1.0000', '6.0000', '6.0000'], [(string) $this->restockIn($rma)->qty_base, $this->restockIn($rma)->cost_unit?->getAmount(), $this->restockIn($rma)->cost_total?->getAmount()]);
        // (36 + 6) / 5
        $this->assertSame(['5.0000', '8.4000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);

        // Quarantäne-Eingang und Umbuchung aus der Quarantäne stehen nicht im Bewertungsbuch.
        $quarantine = StockMovement::query()->where('source_type', MorphMap::alias(ClaimRmaReturn::class))->where('stock_state', StockState::Quality->value)->get();
        $this->assertCount(2, $quarantine);
        $this->assertSame([null, null], $quarantine->map(fn (StockMovement $movement) => $movement->cost_total)->all());
        $this->assertSame('5.0000', app(InventoryLedger::class)->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    /** MVP-1737: Die Portal-Rücksendung ohne Seriennummer speichert ihre Auslieferung — die Wiedereinlagerung nimmt deren Stückwert. */
    public function test_a_portal_return_without_serial_takes_the_cost_of_its_delivery(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '3', '6.00');
        $delivery = app(DeliveryService::class)->deliver($this->variant, $this->warehouse, '2', customer: $this->customer);
        // Danach steigt der Durchschnitt: (1 × 6 + 2 × 12) / 3 = 10.
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '2', '12.00');
        $portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $rma = app(PortalReturnService::class)->submit($portalUser, $this->customer, [
            'delivery_id' => $delivery->id,
            'quantity' => '1',
            'title' => 'Ventil undicht',
            'description' => 'Tropft nach dem Einbau.',
        ])['rma'];
        $this->assertSame([$delivery->id, null], [$rma->stock_delivery_id, $rma->stock_serial_id]);

        $received = app(ClaimRmaService::class)->receive($rma->fresh() ?? $rma, $this->admin, ['warehouse_id' => $this->warehouse->id, 'stock_state' => StockState::Quality]);
        $this->restock($received);

        // Nicht der Durchschnitt (10), sondern der Wert der Auslieferung.
        $this->assertSame(['1.0000', '6.0000', '6.0000'], [(string) $this->restockIn($received)->qty_base, $this->restockIn($received)->cost_unit?->getAmount(), $this->restockIn($received)->cost_total?->getAmount()]);
        // (30 + 6) / 4
        $this->assertSame(['4.0000', '9.0000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
    }

    public function test_without_a_known_delivery_the_restock_takes_the_average(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '4', '9.00');
        $this->variant->forceFill(['purchase_price' => '2.00'])->save();

        $rma = $this->returned('2');
        $this->restock($rma);

        // Gegenprobe zur Inventur: nicht der Einkaufspreis, sondern der Durchschnitt.
        $this->assertSame(['9.0000', '18.0000'], [$this->restockIn($rma)->cost_unit?->getAmount(), $this->restockIn($rma)->cost_total?->getAmount()]);
        $this->assertSame(['6.0000', '9.0000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
    }

    public function test_fifo_restock_adds_a_layer_at_the_delivered_cost(): void {
        $this->article->update(['valuation_method' => 'fifo']);
        Carbon::setTestNow('2026-02-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '2', '3.00');
        Carbon::setTestNow('2026-03-01 08:00:00');
        $serial = $this->shipSerial('SN-V15-2');
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '2', '8.00');
        Carbon::setTestNow();

        $rma = $this->returned('1', $serial);
        $this->restock($rma);

        // Nicht der Schnitt der Restschichten ((3 + 16) / 3), sondern der Wert der Auslieferung.
        $layer = StockValuationLayer::query()->where('source_movement_id', $this->restockIn($rma)->id)->sole();
        $this->assertSame(['1.0000', '3.0000'], [bcadd((string) $layer->qty_remaining, '0', 4), $layer->unit_cost?->getAmount()]);
        $this->assertFalse(StockValuation::query()->where('article_variant_id', $this->variant->id)->exists(), 'Das Durchschnittsbuch bleibt bei FIFO still.');
    }

    public function test_a_repeated_disposition_values_the_restock_once(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '4', '5.00');
        $rma = $this->returned('1');

        $handler = app(InventoryRmaStockHandler::class);
        $handler->applyDisposition($rma->fresh() ?? $rma, ClaimRmaDisposition::Restock, $this->admin);
        $handler->applyDisposition($rma->fresh() ?? $rma, ClaimRmaDisposition::Restock, $this->admin);

        $this->assertSame(1, StockMovement::query()->where('idempotency_key', 'claim-rma:' . $rma->id . ':restock-in')->count());
        $this->assertSame('5.0000', bcadd((string) $this->book()->qty_on_hand, '0', 4));
        $this->assertSame('5.0000', app(InventoryLedger::class)->balance($this->variant, $this->warehouse, StockState::Physical));
    }

    public function test_without_any_book_the_purchase_price_values_the_restock(): void {
        $this->variant->forceFill(['purchase_price' => '4.50'])->save();

        $rma = $this->returned('2');
        $this->restock($rma);

        $this->assertSame(['4.5000', '9.0000'], [$this->restockIn($rma)->cost_unit?->getAmount(), $this->restockIn($rma)->cost_total?->getAmount()]);
        $this->assertSame(['2.0000', '4.5000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
    }
}
