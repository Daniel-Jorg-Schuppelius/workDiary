<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogPriceAlertTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Article\PricingChangeAlertStatus;
use App\Enums\Procurement\CatalogItemStatus;
use App\Models\Article\{Article, PricingChangeAlert, PricingMarginRule};
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\{ManufacturingOrder, ManufacturingOrderMaterial};
use App\Models\Platform\User;
use App\Models\Supplier\{Supplier, SupplierCatalogItem, SupplierCatalogSource};
use App\Services\Procurement\{CatalogCsvImportService, PurchaseOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050, MVP-094: Kalkulationswarnung, wenn ein EK-Anstieg den
 * hinterlegten Verkaufspreis eines verknüpften Artikels unter die Mindestmarge
 * drückt.
 */
final class CatalogPriceAlertTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private SupplierCatalogSource $source;
    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'K', 'format' => 'csv', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
        ]);
        $this->article = Article::factory()->create([
            'organization_id' => $this->organization->id, 'purchasable' => true, 'default_sale_price' => '100.0000',
        ]);
        PricingMarginRule::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'R', 'min_margin' => '30',
            'rounding' => 'none', 'priority' => 0, 'active' => true,
        ]);
    }

    private function linkedItem(string $price): SupplierCatalogItem {
        return SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->supplier->id,
            'external_no' => 'A-1', 'name' => 'Schraube',
            'purchase_price' => $price, 'currency' => 'EUR', 'pack_size' => '1',
            'article_id' => $this->article->id, 'status' => CatalogItemStatus::Linked->value, 'raw_hash' => 'seed',
        ]);
    }

    private function importPrice(string $ek): void {
        $csv = "ArtNr;Bezeichnung;EK\nA-1;Schraube;{$ek}";
        app(CatalogCsvImportService::class)->import($this->source, $csv, [
            'external_no' => 'ArtNr', 'name' => 'Bezeichnung', 'purchase_price' => 'EK',
        ]);
    }

    public function test_price_increase_below_min_margin_creates_alert(): void {
        $this->linkedItem('50.0000');      // Marge 50 %
        $this->importPrice('90,00');       // → Marge 10 % < 30 %

        $this->assertDatabaseHas('pricing_change_alerts', [
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'new_purchase_price' => '90.0000',
            'status' => PricingChangeAlertStatus::Open->value,
        ]);
    }

    public function test_price_change_within_margin_creates_no_alert(): void {
        $this->linkedItem('50.0000');
        $this->importPrice('60,00');       // → Marge 40 % ≥ 30 %

        $this->assertSame(0, PricingChangeAlert::query()->count());
    }

    public function test_unlinked_item_creates_no_alert(): void {
        SupplierCatalogItem::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_source_id' => $this->source->id,
            'supplier_id' => $this->supplier->id,
            'external_no' => 'A-1', 'name' => 'Schraube', 'purchase_price' => '50.0000',
            'currency' => 'EUR', 'pack_size' => '1',
            'status' => CatalogItemStatus::New->value, 'raw_hash' => 'seed',
        ]);
        $this->importPrice('90,00');

        $this->assertSame(0, PricingChangeAlert::query()->count());
    }

    public function test_margin_alert_carries_open_document_impacts(): void {
        $this->linkedItem('50.0000');
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(PurchaseOrderService::class);
        $po = $service->createDraft($this->organization, $this->supplier, $warehouse);
        $service->addLine($po, $this->article, '5');

        $this->importPrice('90,00');

        $alert = PricingChangeAlert::query()->firstOrFail();
        $this->assertSame(PricingChangeAlert::TYPE_MARGIN, $alert->type);
        $this->assertContains($po->number, $alert->impacts['purchase_orders'] ?? []);
    }

    public function test_availability_change_with_open_documents_creates_availability_alert(): void {
        $item = $this->linkedItem('50.0000');
        $item->forceFill(['availability' => 'lieferbar'])->save();

        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => Article::factory()->create(['organization_id' => $this->organization->id])->id,
            'number' => 'FA-TEST-1',
        ]);
        ManufacturingOrderMaterial::query()->create([
            'manufacturing_order_id' => $order->id,
            'article_id' => $this->article->id,
            'name_snapshot' => $this->article->name,
            'target_qty' => '3',
            'unit_snapshot' => 'Stk',
        ]);

        $csv = "ArtNr;Bezeichnung;EK;Verf\nA-1;Schraube;50,00;ausverkauft";
        app(CatalogCsvImportService::class)->import($this->source, $csv, [
            'external_no' => 'ArtNr', 'name' => 'Bezeichnung', 'purchase_price' => 'EK', 'availability' => 'Verf',
        ]);

        $alert = PricingChangeAlert::query()->where('type', PricingChangeAlert::TYPE_AVAILABILITY)->firstOrFail();
        $this->assertSame(['old' => 'lieferbar', 'new' => 'ausverkauft'], $alert->impacts['availability']);
        $this->assertContains($order->number, $alert->impacts['manufacturing_orders']);
    }

    public function test_availability_change_without_open_documents_creates_no_alert(): void {
        $item = $this->linkedItem('50.0000');
        $item->forceFill(['availability' => 'lieferbar'])->save();

        $csv = "ArtNr;Bezeichnung;EK;Verf\nA-1;Schraube;50,00;ausverkauft";
        app(CatalogCsvImportService::class)->import($this->source, $csv, [
            'external_no' => 'ArtNr', 'name' => 'Bezeichnung', 'purchase_price' => 'EK', 'availability' => 'Verf',
        ]);

        $this->assertSame(0, PricingChangeAlert::query()->where('type', PricingChangeAlert::TYPE_AVAILABILITY)->count());
    }

    public function test_alerts_page_renders_both_alert_types_with_impacts(): void {
        $item = $this->linkedItem('90.0000');
        PricingChangeAlert::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_item_id' => $item->id,
            'article_id' => $this->article->id, 'supplier_id' => $this->supplier->id,
            'type' => PricingChangeAlert::TYPE_MARGIN,
            'new_purchase_price' => '90.0000', 'sale_price' => '100.0000', 'new_margin' => '10',
            'min_margin' => '30', 'status' => PricingChangeAlertStatus::Open,
            'impacts' => ['purchase_orders' => ['B-000042'], 'boq_items' => [], 'manufacturing_orders' => []],
        ]);
        PricingChangeAlert::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_item_id' => $item->id,
            'article_id' => $this->article->id, 'supplier_id' => $this->supplier->id,
            'type' => PricingChangeAlert::TYPE_AVAILABILITY,
            'status' => PricingChangeAlertStatus::Open,
            'impacts' => [
                'purchase_orders' => [], 'boq_items' => [], 'manufacturing_orders' => ['FA-000007'],
                'availability' => ['old' => 'lieferbar', 'new' => 'ausverkauft'],
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.alerts'))
            ->assertOk()
            ->assertSee('B-000042')
            ->assertSee('FA-000007')
            ->assertSee('ausverkauft');
    }

    public function test_acknowledge_route_marks_done(): void {
        $alert = PricingChangeAlert::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_catalog_item_id' => $this->linkedItem('90.0000')->id,
            'article_id' => $this->article->id, 'supplier_id' => $this->supplier->id,
            'new_purchase_price' => '90.0000', 'sale_price' => '100.0000', 'new_margin' => '10',
            'min_margin' => '30', 'status' => PricingChangeAlertStatus::Open,
        ]);

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.alerts.acknowledge', $alert))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(PricingChangeAlertStatus::Acknowledged, $alert->fresh()->status);
    }

    /**
     * MVP-1581: der Stammpreis hält die Mindestmarge, ein offenes Angebot mit
     * altem, niedrigerem Belegpreis nicht — gemessen wird die Position.
     */
    public function test_open_quote_below_min_margin_creates_alert_with_document_price(): void {
        $customer = \App\Models\Customer\Customer::factory()->create(['organization_id' => $this->organization->id]);
        $quote = \App\Models\Sales\Quote::factory()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'A-000077',
            'status' => \App\Enums\Sales\QuoteStatus::Sent,
        ]);
        \App\Models\Sales\QuoteItem::factory()->create([
            'organization_id' => $this->organization->id, 'quote_id' => $quote->id, 'article_id' => $this->article->id,
            'position' => 3, 'quantity' => '2.00', 'unit_price' => '70.00',
        ]);
        // Abgelehntes Angebot und stornierter Auftrag zählen nicht.
        $rejected = \App\Models\Sales\Quote::factory()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'A-000078',
            'status' => \App\Enums\Sales\QuoteStatus::Rejected,
        ]);
        \App\Models\Sales\QuoteItem::factory()->create([
            'organization_id' => $this->organization->id, 'quote_id' => $rejected->id, 'article_id' => $this->article->id,
            'unit_price' => '55.00',
        ]);
        $order = \App\Models\Sales\OrderConfirmation::factory()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'AB-000012',
            'status' => \App\Enums\Sales\OrderConfirmationStatus::Confirmed,
        ]);
        \App\Models\Sales\OrderConfirmationItem::factory()->create([
            'organization_id' => $this->organization->id, 'order_confirmation_id' => $order->id, 'article_id' => $this->article->id,
            'position' => 1, 'quantity' => '1.0000', 'unit_price' => '100.00',
        ]);

        $this->linkedItem('50.0000');
        $this->importPrice('60,00');       // Stamm: 40 % ≥ 30 %; Angebot zu 70: 14,3 % < 30 %

        $alert = PricingChangeAlert::query()->sole();
        $this->assertSame(['A-000077'], $alert->impacts['quotes']);
        $this->assertSame(['AB-000012'], $alert->impacts['orders']);
        $this->assertCount(1, $alert->impacts['document_margins']);
        $entry = $alert->impacts['document_margins'][0];
        $this->assertSame(['quotes', 'A-000077', 3], [$entry['group'], $entry['number'], $entry['position']]);
        $this->assertEqualsWithDelta(70.0, $entry['unit_price'], 0.0001);
        $this->assertEqualsWithDelta(14.286, $entry['margin'], 0.001);

        $this->actingAs($this->admin)
            ->get(route('supplier-catalogs.alerts'))
            ->assertOk()
            ->assertSee('A-000077')
            ->assertSee('AB-000012');
    }

    /** MVP-1581: Gewichte des Gesamt-Scores je Organisation, Eingabe in Prozent. */
    public function test_scorecard_weights_are_saved_per_organization(): void {
        $service = app(\App\Services\Procurement\SupplierScorecardService::class);
        $this->assertSame(0.35, $service->weights()['ontime']);

        $this->actingAs($this->admin)->get(route('supplier-scorecards.weights.edit'))->assertOk();
        $this->actingAs($this->admin)->put(route('supplier-scorecards.weights.update'), [
            'weights' => ['ontime' => '50', 'complaints' => '25', 'quality' => '', 'price' => '0', 'recourse' => '12.5'],
        ])->assertRedirect(route('supplier-scorecards.index'))->assertSessionHasNoErrors();

        $settings = $this->organization->fresh()->settings;
        $this->assertEqualsWithDelta(0.5, data_get($settings, 'scorecard.weight.ontime'), 0.00001);
        $this->assertNull(data_get($settings, 'scorecard.weight.quality'));

        app()->instance('currentOrganization', $this->organization->fresh());
        $weights = $service->weights();
        $this->assertEqualsWithDelta(0.5, $weights['ontime'], 0.00001);
        $this->assertEqualsWithDelta(0.2, $weights['quality'], 0.00001); // Vorgabe
        $this->assertEqualsWithDelta(0.0, $weights['price'], 0.00001);
        $this->assertEqualsWithDelta(0.125, $weights['recourse'], 0.00001);

        $this->actingAs($this->admin)->put(route('supplier-scorecards.weights.update'), [
            'weights' => ['ontime' => '0', 'complaints' => '0', 'quality' => '0', 'price' => '0', 'recourse' => '0'],
        ])->assertSessionHasErrors('weights');

        $this->actingAs($this->admin)->get(route('supplier-scorecards.index'))->assertOk()->assertSee(__('scorecard.weights.action'));
    }
}
