<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomsInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Enums\Shipping\ShipmentExportReason;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, Warehouse};
use App\Models\Platform\User;
use App\Services\Inventory\InventoryLedger;
use App\Services\Manufacturing\{DeliveryService, ManufacturingOrderService};
use App\Settings\{SettingScope, SettingsRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1007: Handels- und Proformarechnung zur Auslieferung. */
final class CustomsInvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Article $article;

    /** @var array<string, mixed> */
    private array $pdfData = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Steuergerät']);
        View::composer('pdf.customs-invoice', function (\Illuminate\View\View $view): void {
            $this->pdfData = $view->getData();
        });
    }

    private function delivery(string $country): StockDelivery {
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $this->article->id,
        ]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $order = app(ManufacturingOrderService::class)->createDraft($this->organization, $this->article, $variant, '5', 'Stk', ['warehouse_id' => $warehouse->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id, 'name' => 'Alpen AG', 'address_street' => 'Bahnhofstr. 1',
            'address_zip' => '8001', 'address_city' => 'Zürich', 'country' => $country,
        ]);
        app(InventoryLedger::class)->receipt($variant, $warehouse, '10');
        $delivery = app(DeliveryService::class)->deliver($variant, $warehouse, '3', $order, $customer);
        $delivery->update(['unit_price_snapshot' => '19.99']);

        return $delivery->refresh();
    }

    public function test_customs_documents_need_article_data_and_follow_the_reason(): void {
        $delivery = $this->delivery('CH');
        $order = $delivery->order;

        $this->actingAs($this->admin)->get(route('manufacturing-orders.deliveries.customs.form', [$order, $delivery]))->assertOk()
            ->assertSee(__('shipping.customs.required_hint'))->assertSee(__('article.field.customs_tariff_number'))
            ->assertDontSee(__('shipping.customs.submit'));
        $this->actingAs($this->admin)->post(route('manufacturing-orders.deliveries.customs.pdf', [$order, $delivery]), ['export_reason' => 'sale'])
            ->assertSessionHasErrors('customs');

        $this->actingAs($this->admin)->put(route('articles.update', $this->article), [
            'name' => 'Steuergerät', 'type' => $this->article->type->value, 'base_unit' => 'Stk', 'status' => $this->article->status->value,
            'customs_tariff_number' => '8537 10 99', 'origin_country' => 'de', 'net_weight_kg' => '1.25',
        ])->assertSessionHasNoErrors();
        $this->assertSame('85371099', $this->article->refresh()->customs_tariff_number);
        $this->assertSame('DE', $this->article->origin_country);
        $this->actingAs($this->admin)->put(route('articles.update', $this->article), [
            'name' => 'Steuergerät', 'type' => $this->article->type->value, 'base_unit' => 'Stk', 'status' => $this->article->status->value,
            'customs_tariff_number' => '12AB', 'origin_country' => 'XX',
        ])->assertSessionHasErrors(['customs_tariff_number', 'origin_country']);

        app(SettingsRegistry::class)->set('shipping.eori_number', 'de1234567', SettingScope::Organization, $this->organization);
        $this->actingAs($this->admin)->get(route('manufacturing-orders.deliveries.customs.form', [$order, $delivery]))->assertOk()
            ->assertSee(__('shipping.customs.submit'));

        $response = $this->actingAs($this->admin)->post(route('manufacturing-orders.deliveries.customs.pdf', [$order, $delivery]), ['export_reason' => 'sale']);
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertSame(ShipmentExportReason::Sale, $delivery->refresh()->export_reason);
        $this->assertSame(__('shipping.customs.commercial_invoice'), $this->pdfData['title']);
        $this->assertSame('HR-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT), $this->pdfData['number']);
        $this->assertSame('DE1234567', $this->pdfData['sender']['eori']);
        $this->assertSame(['description' => $delivery->name_snapshot, 'sku' => $delivery->sku_snapshot, 'tariff' => '85371099', 'origin' => 'DE', 'quantity' => '3', 'unit' => 'Stk',
            'net_weight' => '3,750', 'unit_value' => '19,99 €', 'total_value' => '59,97 €', 'currency' => 'EUR'], $this->pdfData['position']);

        $this->actingAs($this->admin)->post(route('manufacturing-orders.deliveries.customs.pdf', [$order, $delivery]), ['export_reason' => 'sample'])->assertOk();
        $this->assertSame(__('shipping.customs.proforma_invoice'), $this->pdfData['title']);
        $this->assertFalse($this->pdfData['commercial']);
        $this->assertSame(ShipmentExportReason::Sample, $delivery->refresh()->export_reason);
    }

    public function test_eu_destinations_get_a_hint_and_viewers_are_rejected(): void {
        $delivery = $this->delivery('AT');
        $this->actingAs($this->admin)->get(route('manufacturing-orders.deliveries.customs.form', [$delivery->order, $delivery]))->assertOk()
            ->assertSee(__('shipping.customs.eu_hint'));

        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($viewer)->get(route('manufacturing-orders.deliveries.customs.form', [$delivery->order, $delivery]))->assertForbidden();
    }
}
