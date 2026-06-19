<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialIntegrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Inventory\{SerialSource, SerialStatus};
use App\Models\{Article, ArticleVariant, Customer, ProcedureMaterialRequirement, ProcedureTemplateVersion, StockSerial, Warehouse};
use App\Services\Manufacturing\{DeliveryService, ManufacturingOrderService, ManufacturingReportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * E2-Integration: Gutmeldung eines seriennummernpflichtigen Erzeugnisses erzeugt
 * je Stück eine Seriennummer; die Auslieferung bindet konkrete Seriennummern an
 * den Kunden (Versandnachweis).
 */
final class SerialIntegrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_finished_good_report_generates_serials_and_delivery_ships_them(): void {
        $this->setUpOrganization();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $version = ProcedureTemplateVersion::factory()->create();
        ProcedureMaterialRequirement::factory()->perUnit('1')->create([
            'procedure_template_version_id' => $version->id,
            'article_id' => Article::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        $product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'serial_required' => true,
            'default_procedure_template_version_id' => $version->id,
        ]);
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $product->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);

        $orders = app(ManufacturingOrderService::class);
        $order = $orders->release($orders->createDraft($this->organization, $product, null, '2', 'Stk', ['warehouse_id' => $warehouse->id]));

        // Gutmeldung 2 Stück → 2 Seriennummern (Eigenfertigung, auf Lager).
        app(ManufacturingReportService::class)->report($order, '2', '2', '0', '0', null);

        $serials = StockSerial::query()->where('manufacturing_order_id', $order->id)->get();
        $this->assertCount(2, $serials);
        $this->assertTrue($serials->every(fn (StockSerial $s) => $s->status === SerialStatus::InStock && $s->source === SerialSource::Manufactured));

        // Auslieferung bindet genau diese Seriennummern an den Kunden.
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        app(DeliveryService::class)->deliver($variant, $warehouse, '2', $order, $customer, serialIds: $serials->pluck('id')->all());

        $shipped = StockSerial::query()->where('status', SerialStatus::Shipped->value)->where('customer_id', $customer->id)->count();
        $this->assertSame(2, $shipped);
    }
}
