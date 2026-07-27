<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DespatchAdviceImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\{AdviceStatus, PurchaseOrderStatus};
use App\Models\{Article, ArticleVariant, PurchaseOrder, Supplier, Warehouse};
use App\Services\Procurement\{AdviceService, DespatchAdviceImportService, PurchaseOrderService};
use DateTimeImmutable;
use ERechnungToolkit\Builders\DespatchAdviceBuilder;
use ERechnungToolkit\Enums\UnitCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Beschaffung (E4): Import eines elektronischen Lieferscheins (UBL Despatch
 * Advice) als Lieferavis und anschließende Wareneingangsbuchung — die eingehende
 * Gegenrichtung zum Bestellexport.
 */
final class DespatchAdviceImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Supplier $supplier;
    private Article $article;
    private Warehouse $warehouse;
    private PurchaseOrder $order;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);

        $orders = app(PurchaseOrderService::class);
        $this->order = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($this->order, $this->article, '10', ['supplier_sku' => 'SUP-1']);
        $orders->addLine($this->order, $this->article, '5', ['supplier_sku' => 'SUP-2']);
        $orders->submit($this->order);
    }

    private function despatchXml(string $reference = 'LS-9001'): string {
        return DespatchAdviceBuilder::create($reference)
            ->withIssueDate(new DateTimeImmutable('2026-06-26'))
            ->withOrderReference($this->order->number)
            ->withSupplier((string) $this->supplier->name, 'DE222222222')
            ->withSupplierAddress('Lieferweg 2', '54321', 'Lieferstadt')
            ->withCustomer('Meine Firma GmbH')
            ->withCustomerAddress('Firmenweg 1', '10115', 'Berlin')
            ->withActualDeliveryDate(new DateTimeImmutable('2026-07-01'))
            ->addLine('Bürostuhl', 4, UnitCode::PIECE, '1', 'SUP-1')   // Teillieferung: 4 von 10
            ->addLine('Tisch', 2, UnitCode::PIECE, '2', 'SUP-2')
            ->build()
            ->toUblXml();
    }

    public function test_import_creates_announced_advice_matched_to_order_lines(): void {
        $advice = app(DespatchAdviceImportService::class)->import($this->despatchXml());

        $this->assertSame($this->order->id, $advice->purchase_order_id);
        $this->assertSame(AdviceStatus::Announced, $advice->status);
        $this->assertSame('LS-9001', $advice->reference);
        $this->assertSame('2026-07-01', $advice->expected_at?->toDateString());

        $lines = $this->order->lines()->orderBy('id')->get();
        $adviceLines = $advice->lines()->get()->keyBy('purchase_order_line_id');
        $this->assertCount(2, $adviceLines);
        $this->assertSame('4.0000', $adviceLines[$lines[0]->id]->qty);
        $this->assertSame('2.0000', $adviceLines[$lines[1]->id]->qty);
    }

    public function test_import_then_receive_books_goods_receipt(): void {
        $advice = app(DespatchAdviceImportService::class)->import($this->despatchXml());
        app(AdviceService::class)->receive($advice);

        $lines = $this->order->lines()->orderBy('id')->get();
        $this->assertSame('4.0000', $lines[0]->fresh()->received_qty?->getNumericValue());
        $this->assertSame('2.0000', $lines[1]->fresh()->received_qty?->getNumericValue());
        $this->assertSame(AdviceStatus::Received, $advice->fresh()->status);
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $this->order->fresh()->status);
    }

    public function test_import_matches_by_supplier_sku_when_line_number_absent(): void {
        // Lieferschein ohne OrderLineReference → Zuordnung über Lieferanten-SKU.
        $xml = DespatchAdviceBuilder::create('LS-9002')
            ->withOrderReference($this->order->number)
            ->withSupplier((string) $this->supplier->name, 'DE222222222')
            ->withSupplierAddress('Lieferweg 2', '54321', 'Lieferstadt')
            ->withCustomer('Meine Firma GmbH')
            ->withCustomerAddress('Firmenweg 1', '10115', 'Berlin')
            ->addLine('Tisch', 3, UnitCode::PIECE, null, 'SUP-2')
            ->build()
            ->toUblXml();

        $advice = app(DespatchAdviceImportService::class)->import($xml);

        $secondLine = $this->order->lines()->orderBy('id')->get()[1];
        $this->assertSame('3.0000', $advice->lines()->firstOrFail()->qty);
        $this->assertSame($secondLine->id, $advice->lines()->firstOrFail()->purchase_order_line_id);
    }

    public function test_import_rejects_unknown_order_reference(): void {
        $xml = DespatchAdviceBuilder::create('LS-9003')
            ->withOrderReference('BE-DOES-NOT-EXIST')
            ->withSupplier('X', 'DE222222222')
            ->withSupplierAddress('a', '1', 'b')
            ->withCustomer('Y')
            ->withCustomerAddress('c', '2', 'd')
            ->addLine('Ware', 1, UnitCode::PIECE, '1')
            ->build()
            ->toUblXml();

        $this->expectException(RuntimeException::class);
        app(DespatchAdviceImportService::class)->import($xml);
    }
}
