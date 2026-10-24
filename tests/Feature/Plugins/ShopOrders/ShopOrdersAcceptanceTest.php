<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrdersAcceptanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\ShopOrders;

use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\{Organization, User};
use App\Plugins\PrestaShop\Models\{PrestaShopConnection, PrestaShopOrder};
use App\Plugins\PrestaShop\PrestaShopPlugin;
use App\Plugins\PrestaShop\Services\PrestaShopOrderImportService;
use App\Plugins\Shopware\Models\{ShopwareConnection, ShopwareOrder};
use App\Plugins\Shopware\Services\ShopwareOrderImportService;
use App\Plugins\Shopware\ShopwarePlugin;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Plugins\WooCommerce\Models\{WooCommerceConnection, WooCommerceOrder};
use App\Plugins\WooCommerce\Services\WooCommerceOrderImportService;
use App\Plugins\WooCommerce\WooCommercePlugin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Gesamtabnahme der Shop-Importe (MVP-1330): drei Shops in einer Organisation
 * nebeneinander, derselbe Käufer je Shop als eigener Klärfall, Wiederholungen
 * ohne Dubletten, fremde Organisationen sehen weder Bestellungen noch Seite.
 */
class ShopOrdersAcceptanceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);
        $this->pluginSecret(WooCommercePlugin::ID, ['base_url' => 'https://woo.example.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs']);
        $this->pluginSecret(ShopwarePlugin::ID, ['base_url' => 'https://sw.example.test', 'client_id' => 'SWIA', 'client_secret' => 'geheim']);
        $this->pluginSecret(PrestaShopPlugin::ID, ['base_url' => 'https://ps.example.test', 'api_key' => 'PSKEY']);
        foreach ([WooCommerceConnection::class => 'https://woo.example.test', ShopwareConnection::class => 'https://sw.example.test', PrestaShopConnection::class => 'https://ps.example.test'] as $model => $base) {
            $this->connect($model, $this->organization, $base);
        }
    }

    /** @param class-string<Model> $model */
    private function connect(string $model, Organization $organization, string $base): void {
        $model::query()->forceCreate([
            'organization_id' => $organization->id,
            'source_id' => SelfHostedInstance::key($base),
            'source_url' => $base,
            'status' => OAuthConnectionStatus::Active,
            'import_confirmed_at' => now(),
        ]);
    }

    private function serve(): void {
        $buyer = ['first_name' => 'Kim', 'last_name' => 'Kauf', 'company' => '', 'email' => 'kim@example.test'];
        FakePluginHttp::fake([
            'https://woo.example.test/wp-json/wc/v3/orders*' => FakePluginHttp::response([
                ['id' => 1, 'number' => '1', 'status' => 'processing', 'currency' => 'EUR', 'total' => '10.00', 'total_tax' => '0.00', 'date_created_gmt' => '2026-10-20T08:00:00', 'date_modified_gmt' => '2026-10-20T08:00:00', 'customer_id' => 0, 'billing' => $buyer, 'line_items' => []],
            ], 200, ['X-WP-TotalPages' => '1']),
            'https://sw.example.test/api/oauth/token' => FakePluginHttp::response(['access_token' => 't', 'expires_in' => 600, 'token_type' => 'Bearer']),
            'https://sw.example.test/api/search/order' => FakePluginHttp::response(['total' => 1, 'data' => [
                ['id' => str_repeat('a', 32), 'orderNumber' => '1', 'orderDateTime' => '2026-10-20T08:00:00.000+00:00', 'amountTotal' => 10, 'amountNet' => 10, 'createdAt' => '2026-10-20T08:00:00.000+00:00', 'updatedAt' => null, 'currency' => ['isoCode' => 'EUR'], 'stateMachineState' => ['technicalName' => 'open'], 'orderCustomer' => ['customerId' => str_repeat('b', 32), 'email' => 'kim@example.test', 'firstName' => 'Kim', 'lastName' => 'Kauf', 'customer' => ['guest' => true]], 'lineItems' => []],
            ]]),
            'https://ps.example.test/api/orders*' => FakePluginHttp::response(['orders' => [
                ['id' => 1, 'id_customer' => '3', 'id_currency' => '1', 'current_state' => '2', 'reference' => 'PSREF', 'total_paid_tax_incl' => '10.00', 'total_paid_tax_excl' => '10.00', 'date_add' => '2026-10-20 08:00:00', 'date_upd' => '2026-10-20 08:00:00'],
            ]]),
            'https://ps.example.test/api/customers*' => FakePluginHttp::response(['customers' => [['id' => 3, 'firstname' => 'Kim', 'lastname' => 'Kauf', 'email' => 'kim@example.test', 'company' => '', 'is_guest' => '1']]]),
            'https://ps.example.test/api/currencies*' => FakePluginHttp::response(['currencies' => [['id' => 1, 'iso_code' => 'EUR']]]),
            'https://ps.example.test/api/order_states*' => FakePluginHttp::response(['order_states' => [['id' => 2, 'template' => 'payment', 'paid' => '1', 'shipped' => '0', 'delivery' => '0', 'logable' => '1']]]),
        ]);
    }

    private function runAll(): void {
        app(WooCommerceOrderImportService::class)->run($this->organization);
        app(ShopwareOrderImportService::class)->run($this->organization);
        app(PrestaShopOrderImportService::class)->run($this->organization);
    }

    public function test_three_shops_side_by_side_without_duplicates_on_replay(): void {
        $this->serve();
        $this->runAll();
        $this->runAll();

        $this->assertSame(1, WooCommerceOrder::query()->count());
        $this->assertSame(1, ShopwareOrder::query()->count());
        $this->assertSame(1, PrestaShopOrder::query()->count());
        foreach ([WooCommerceOrder::class, ShopwareOrder::class, PrestaShopOrder::class] as $model) {
            $this->assertSame('guest:kim@example.test', $model::query()->sole()->getAttribute('buyer_external_id'));
        }
        foreach ([WooCommercePlugin::ID, ShopwarePlugin::ID, PrestaShopPlugin::ID] as $plugin) {
            $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', $plugin)->count(), "Ein Klärfall je Shop und Käufer ({$plugin}), auch nach der Wiederholung.");
        }
    }

    public function test_other_organization_sees_nothing(): void {
        $this->serve();
        $this->runAll();

        $other = Organization::factory()->create();
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $other->id]);
        $this->pluginSecret(WooCommercePlugin::ID, ['base_url' => 'https://woo-b.example.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs'], (int) $other->id);
        $this->connect(WooCommerceConnection::class, $other, 'https://woo-b.example.test');

        $this->actingAs($otherAdmin)->get(route('admin.woocommerce.index'))->assertOk()
            ->assertViewHas('orders', static fn ($orders): bool => $orders->total() === 0)
            ->assertSee(__('integration.shop_orders.orders.empty'));
        $this->assertSame(0, WooCommerceOrder::query()->withoutGlobalScopes()->where('organization_id', $other->id)->count());
    }
}
