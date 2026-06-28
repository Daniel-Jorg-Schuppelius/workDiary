<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{Article, ArticleVariant, PurchaseOrder, Supplier, User, Warehouse};
use App\Services\Procurement\{GoodsReceiptService, PurchaseOrderService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Beschaffungs-UI (Feature 048, E4): Berechtigungen, Anlage, Bestellen und
 * Wareneingang gegen die Bestellzeile über HTTP.
 */
final class PurchaseOrderControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true, 'base_unit' => 'Stk']);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_index_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('purchase-orders.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('purchase-orders.index'))->assertOk();
    }

    public function test_store_creates_draft(): void {
        $response = $this->actingAs($this->admin)->post(route('purchase-orders.store'), [
            'supplier' => $this->supplier->sqid,
            'warehouse' => $this->warehouse->sqid,
        ]);

        $order = PurchaseOrder::query()->firstOrFail();
        $response->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertStringStartsWith('BE-', $order->number);
    }

    public function test_add_line_submit_and_receive_flow(): void {
        $order = app(PurchaseOrderService::class)->createDraft($this->organization, $this->supplier, $this->warehouse);

        $this->actingAs($this->admin)->post(route('purchase-orders.lines.add', $order), [
            'article' => $this->article->sqid, 'qty' => '10', 'unit_price' => '2',
        ])->assertRedirect();
        $line = $order->lines()->firstOrFail();
        $this->assertSame('10.0000', $line->ordered_qty);

        $this->actingAs($this->admin)->post(route('purchase-orders.submit', $order))->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::Ordered, $order->fresh()->status);

        $this->actingAs($this->admin)->post(route('purchase-orders.receive', $order), [
            'line' => $line->sqid, 'qty' => '10',
        ])->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertSame('10.0000', $line->fresh()->received_qty);
    }

    public function test_suggestions_page_renders(): void {
        $this->actingAs($this->admin)->get(route('purchase-orders.suggestions'))->assertOk();
    }

    public function test_incoming_lists_open_lines(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $line = $orders->addLine($po, $this->article, '10');
        $orders->submit($po);
        app(GoodsReceiptService::class)->receive($line, '4'); // Teil → offen 6

        $this->actingAs($this->admin)->get(route('purchase-orders.incoming'))->assertOk()->assertSee($po->number);
    }

    public function test_download_order_xml_returns_xbestellung(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '5', ['unit_price' => '12']);
        $orders->submit($po);

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.order-xml', $po));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="XBestellung_', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<Order', $response->getContent());
        $this->assertStringContainsString('urn:fdc:peppol.eu:poacc:trns:order:3', $response->getContent());
    }

    public function test_download_order_xml_orderx_format(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '5', ['unit_price' => '12']);
        $orders->submit($po);

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.order-xml', ['purchaseOrder' => $po, 'format' => 'orderx']));

        $response->assertOk();
        $this->assertStringContainsString('SCRDMCCBDACIOMessageStructure', $response->getContent());
    }

    public function test_download_order_xml_opentrans_format(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '5', ['unit_price' => '12']);
        $orders->submit($po);

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.order-xml', ['purchaseOrder' => $po, 'format' => 'opentrans']));

        $response->assertOk();
        $this->assertStringContainsString('attachment; filename="openTRANS_', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<ORDER', $response->getContent());
        $this->assertStringContainsString('opentrans.org/XMLSchema/2.1', $response->getContent());
    }

    public function test_update_conditions_sets_freight_cost(): void {
        $po = app(PurchaseOrderService::class)->createDraft($this->organization, $this->supplier, $this->warehouse);

        $this->actingAs($this->admin)
            ->post(route('purchase-orders.conditions', $po), ['freight_cost' => '25.50'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('25.5000', $po->fresh()->freight_cost);
    }

    public function test_download_order_xml_ugl_format(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '5', ['unit_price' => '12']);
        $orders->submit($po);

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.order-xml', ['purchaseOrder' => $po, 'format' => 'ugl']));

        $response->assertOk();
        $this->assertStringContainsString('attachment; filename="UGL_', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.ugl"', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('KOP', $response->getContent());
    }

    public function test_download_order_xml_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $po = app(PurchaseOrderService::class)->createDraft($this->organization, $this->supplier, $this->warehouse);

        $this->actingAs($stranger)->get(route('purchase-orders.order-xml', $po))->assertForbidden();
    }

    public function test_import_advice_upload_creates_advice(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '10', ['supplier_sku' => 'SUP-1']);
        $orders->submit($po);

        $xml = \ERechnungToolkit\Builders\DespatchAdviceBuilder::create('LS-IMP-1')
            ->withOrderReference($po->number)
            ->withSupplier((string) $this->supplier->name, 'DE222222222')
            ->withSupplierAddress('Lieferweg 2', '54321', 'Lieferstadt')
            ->withCustomer('Wir GmbH')
            ->withCustomerAddress('Firmenweg 1', '10115', 'Berlin')
            ->addLine('Ware', 4, \ERechnungToolkit\Enums\UnitCode::PIECE, '1', 'SUP-1')
            ->build()
            ->toUblXml();

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('LS-IMP-1.xml', $xml);

        $this->actingAs($this->admin)
            ->post(route('purchase-orders.advices.import', $po), ['advice_xml' => $file])
            ->assertRedirect();

        $advice = $po->advices()->firstOrFail();
        $this->assertSame('LS-IMP-1', $advice->reference);
        $this->assertSame('4.0000', $advice->lines()->firstOrFail()->qty);
    }

    public function test_import_advice_rejects_mismatched_order_reference(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '10', ['supplier_sku' => 'SUP-1']);
        $orders->submit($po);

        $xml = \ERechnungToolkit\Builders\DespatchAdviceBuilder::create('LS-IMP-2')
            ->withOrderReference('BE-OTHER-999')
            ->withSupplier('X', 'DE222222222')
            ->withSupplierAddress('a', '1', 'b')
            ->withCustomer('Y')
            ->withCustomerAddress('c', '2', 'd')
            ->addLine('Ware', 1, \ERechnungToolkit\Enums\UnitCode::PIECE, '1', 'SUP-1')
            ->build()
            ->toUblXml();

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('LS-IMP-2.xml', $xml);

        $this->actingAs($this->admin)
            ->post(route('purchase-orders.advices.import', $po), ['advice_xml' => $file])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, $po->advices()->count());
    }

    public function test_purchase_order_pdf_is_streamed(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $orders->addLine($po, $this->article, '5', ['unit_price' => '12']);

        $response = $this->actingAs($this->admin)->get(route('purchase-orders.pdf', $po));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_pdf_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $po = app(PurchaseOrderService::class)->createDraft($this->organization, $this->supplier, $this->warehouse);

        $this->actingAs($stranger)->get(route('purchase-orders.pdf', $po))->assertForbidden();
    }
}
