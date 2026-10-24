<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\ShopOrders;

use App\Enums\Integration\ShopOrderStatus;
use App\Models\Platform\User;
use App\Plugins\PrestaShop\Models\{PrestaShopConnection, PrestaShopOrder};
use App\Plugins\PrestaShop\PrestaShopPlugin;
use App\Plugins\PrestaShop\Services\PrestaShopOrderImportService;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * PrestaShop (MVP-1327): Webservice-Schlüssel als Basic-Auth, Seiten über
 * Keyset-Seiten über `filter[id]=>[n]` mit `[]` als leerer Liste, Kunden je Seite gesammelt,
 * Status aus Vorlage und Merkmalen des Bestellstatus, Zeiten in Shop-Zeit, kein
 * Verwaltungslink.
 */
class PrestaShopImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://presta.example.test';

    /** @var list<array<string, mixed>> */
    private array $orders = [];

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->pluginSecret(PrestaShopPlugin::ID, ['base_url' => self::BASE, 'api_key' => 'PSKEY', 'shop_timezone' => 'Europe/Vienna', 'include_cancelled' => true]);
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);
        config(['plugins.prestashop.page_size' => 2]);

        $this->orders = [
            $this->order(11, 'ABCDEF', 2, 7),
            $this->order(12, 'GHIJKL', 5, 8),
            $this->order(13, 'MNOPQR', 6, 9),
            $this->order(14, 'STUVWX', 3, 7, 4),
            $this->order(15, 'YZABCD', 0, 7),
        ];
    }

    /** @return array<string, mixed> */
    private function order(int $id, string $reference, int $state, int $customer, int $currency = 1): array {
        return [
            'id' => $id,
            'id_customer' => (string) $customer,
            'id_currency' => (string) $currency,
            'current_state' => (string) $state,
            'reference' => $reference,
            'total_paid_tax_incl' => '59.500000',
            'total_paid_tax_excl' => '50.000000',
            'date_add' => '2026-10-19 10:00:00',
            'date_upd' => '2026-10-19 11:30:00',
            'associations' => ['order_rows' => [['product_name' => 'Tasse', 'product_reference' => 'T-1', 'product_quantity' => '2', 'unit_price_tax_incl' => '29.750000']]],
        ];
    }

    private function connect(): PrestaShopConnection {
        return PrestaShopConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'presta.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
            'import_confirmed_at' => now(),
        ]);
    }

    private function serve(): FakePluginHttp {
        return FakePluginHttp::fake([
            self::BASE . '/api/orders*' => function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);
                [$offset, $count] = array_map('intval', explode(',', is_string($query['limit'] ?? null) ? $query['limit'] : '0,100'));
                $after = (int) trim((string) data_get($query, 'filter.id', '>[0]'), '>[]');
                $rows = array_slice(array_values(array_filter($this->orders, static fn (array $order): bool => $order['id'] > $after)), $offset, $count);

                return FakePluginHttp::response($rows === [] ? [] : ['orders' => $rows]);
            },
            self::BASE . '/api/customers*' => FakePluginHttp::response(['customers' => [
                ['id' => 7, 'firstname' => 'Anna', 'lastname' => 'Alt', 'email' => 'anna@example.test', 'company' => 'Alt GmbH', 'is_guest' => '0'],
                ['id' => 8, 'firstname' => 'Gerd', 'lastname' => 'Gast', 'email' => 'Gerd@Example.test', 'company' => '', 'is_guest' => '1'],
                ['id' => 9, 'firstname' => 'Sara', 'lastname' => 'Storno', 'email' => 'sara@example.test', 'company' => '', 'is_guest' => '0'],
            ]]),
            // Die Liste kennt nur die Währungen des Standardshops; Nr. 4 gehört zu einem anderen Shop.
            self::BASE . '/api/currencies/4*' => FakePluginHttp::response(['currency' => ['id' => 4, 'iso_code' => 'chf']]),
            self::BASE . '/api/currencies?*' => FakePluginHttp::response(['currencies' => [['id' => 1, 'iso_code' => 'eur']]]),
            self::BASE . '/api/order_states*' => FakePluginHttp::response(['order_states' => [
                ['id' => 2, 'template' => [['id' => '1', 'value' => 'payment']], 'paid' => '1', 'shipped' => '0', 'logable' => '1'],
                // Wie im Standard „in Vorbereitung“: Lieferschein ja, versendet nein.
                ['id' => 3, 'template' => [['id' => '1', 'value' => 'preparation']], 'paid' => '1', 'shipped' => '0', 'delivery' => '1', 'logable' => '1'],
                ['id' => 5, 'template' => [['id' => '1', 'value' => '']], 'paid' => '1', 'shipped' => '1', 'logable' => '1'],
                ['id' => 6, 'template' => [['id' => '1', 'value' => 'order_canceled']], 'paid' => '0', 'shipped' => '0', 'logable' => '0'],
            ]]),
        ]);
    }

    public function test_import_reads_pages_resolves_lookups_and_maps_states(): void {
        $this->connect();
        $http = $this->serve();

        $counters = app(PrestaShopOrderImportService::class)->run($this->organization);

        $this->assertSame(5, $counters['imported']);
        $paid = PrestaShopOrder::query()->where('remote_order_id', '11')->sole();
        $this->assertSame(ShopOrderStatus::Processing, $paid->status);
        $this->assertSame('state:2', $paid->remote_status_code);
        $this->assertSame('ABCDEF', $paid->order_number);
        $this->assertSame('59.50', $paid->gross_amount?->getAmount());
        $this->assertSame('EUR', $paid->currency?->value);
        $this->assertSame('customer:7', $paid->buyer_external_id);
        $this->assertSame('Alt GmbH', $paid->buyerLabel());
        $this->assertSame('2026-10-19 08:00:00', $paid->ordered_at?->utc()->format('Y-m-d H:i:s'), 'Shop-Zeit Wien → UTC.');
        $this->assertSame(ShopOrderStatus::Completed, PrestaShopOrder::query()->where('remote_order_id', '12')->sole()->status);
        $this->assertSame('guest:gerd@example.test', PrestaShopOrder::query()->where('remote_order_id', '12')->sole()->buyer_external_id);
        $this->assertSame(ShopOrderStatus::Cancelled, PrestaShopOrder::query()->where('remote_order_id', '13')->sole()->status);
        $preparing = PrestaShopOrder::query()->where('remote_order_id', '14')->sole();
        $this->assertSame(ShopOrderStatus::Processing, $preparing->status, '„In Vorbereitung“ zeigt nur den Lieferschein — nicht versendet.');
        $this->assertSame('CHF', $preparing->currency?->value, 'Währung eines anderen Shops über id_shop=all nachgelesen.');
        $this->assertSame(ShopOrderStatus::Open, PrestaShopOrder::query()->where('remote_order_id', '15')->sole()->status, 'Ohne Status noch offen.');
        $this->assertNull(app(PrestaShopOrderImportService::class)->orderUrl($paid), 'Kein Link in die geheime Verwaltung.');

        $http->assertSent(static fn (RequestInterface $r): bool => $r->getHeaderLine('Authorization') === 'Basic ' . base64_encode('PSKEY:'));
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return str_ends_with($r->getUri()->getPath(), '/api/orders') && data_get($q, 'filter.id') === '>[12]' && ($q['limit'] ?? null) === '0,2' && ($q['sort'] ?? null) === '[id_ASC]';
        });
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return str_ends_with($r->getUri()->getPath(), '/api/currencies/4') && ($q['id_shop'] ?? null) === 'all';
        });
        $this->assertTrue(PrestaShopConnection::query()->sole()->is_last_run_complete);
    }

    public function test_empty_shop_answers_with_a_list_and_completes(): void {
        $this->orders = [];
        $this->connect();
        $this->serve();

        $counters = app(PrestaShopOrderImportService::class)->run($this->organization);

        $this->assertSame(0, $counters['imported']);
        $this->assertTrue(PrestaShopConnection::query()->sole()->is_last_run_complete);
    }

    public function test_page_shows_orders_without_shop_link(): void {
        $this->connect();
        $this->serve();
        app(PrestaShopOrderImportService::class)->run($this->organization);

        $this->actingAs($this->admin)->get(route('admin.prestashop.index'))->assertOk()
            ->assertSee('#ABCDEF')
            ->assertDontSee('href="' . self::BASE, false);
    }

    public function test_sync_command_imports_confirmed_connections(): void {
        $this->connect();
        $this->serve();

        $this->artisan('prestashop:sync --full')->assertSuccessful();

        $this->assertSame(5, PrestaShopOrder::query()->count());
    }

    public function test_after_the_clock_goes_back_the_filter_starts_at_the_earliest_local_time(): void {
        // 25.10.2026, 01:30 UTC: in Wien ist es 02:30 MEZ, die Stunde 02:00–03:00 lief schon einmal als MESZ.
        $this->travelTo(CarbonImmutable::parse('2026-10-25 01:30:00', 'UTC'));
        $this->connect()->forceFill(['checkpoint_at' => CarbonImmutable::parse('2026-10-25 00:55:00', 'UTC')])->save();
        $http = $this->serve();

        app(PrestaShopOrderImportService::class)->run($this->organization);

        // Der Aufholpunkt minus Überlappung ist 02:45 MESZ; Änderungen seit der Rückstellung tragen 02:00–02:30.
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return str_ends_with($r->getUri()->getPath(), '/api/orders') && data_get($q, 'filter.date_upd') === '[2026-10-25 02:00:00,2999-12-31 23:59:59]';
        });
    }
}
