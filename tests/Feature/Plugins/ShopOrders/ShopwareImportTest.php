<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwareImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\ShopOrders;

use App\Enums\Integration\ShopOrderStatus;
use App\Models\Platform\User;
use App\Plugins\Shopware\Models\{ShopwareConnection, ShopwareOrder};
use App\Plugins\Shopware\Services\ShopwareOrderImportService;
use App\Plugins\Shopware\ShopwarePlugin;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Shopware 6 (MVP-1326): Anmeldung über Client Credentials mit einem Token je
 * Lauf, Suche mit Kriterien (geändert ODER angelegt seit dem Aufholpunkt,
 * Reihenfolge nach ID, exakte Gesamtzahl), Status aus dem technischen Namen,
 * Gastkonten über die E-Mail, Link in die Shopware-Verwaltung.
 */
class ShopwareImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://shopware.example.test';

    /** @var list<array<string, mixed>> */
    private array $orders = [];

    private ?int $reportedTotal = null;

    /** @var list<string> im Shop gelöschte Bestell-IDs (fehlen in `search-ids`) */
    private array $gone = [];

    /** Bestellungen, die ab Seite 2 nicht mehr mitzählen (gelöscht während des Blätterns). */
    private int $vanished = 0;

    /** @var list<\Psr\Http\Message\ResponseInterface> Antworten vor den regulären (Fehlerfälle) */
    private array $failFirst = [];

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret(ShopwarePlugin::ID, ['base_url' => self::BASE, 'client_id' => 'SWIATEST', 'client_secret' => 'geheim']);
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);
        config(['plugins.shopware.page_size' => 2]);

        $this->orders = [
            $this->order('0a' . str_repeat('1', 30), 'SW-1', 'in_progress', 'c' . str_repeat('1', 31), false, 'kunde@example.test'),
            $this->order('0b' . str_repeat('2', 30), 'SW-2', 'completed', 'c' . str_repeat('2', 31), true, 'Gast@Example.test'),
            $this->order('0c' . str_repeat('3', 30), 'SW-3', 'open', 'c' . str_repeat('3', 31), false, 'drei@example.test'),
        ];
    }

    /** @return array<string, mixed> */
    private function order(string $id, string $number, string $state, string $customerId, bool $guest, string $email): array {
        return [
            'id' => $id,
            'orderNumber' => $number,
            'orderDateTime' => '2026-10-19T09:00:00.000+00:00',
            'amountTotal' => 119.0,
            'amountNet' => 100.0,
            'createdAt' => '2026-10-19T09:00:00.000+00:00',
            'updatedAt' => null,
            'currency' => ['isoCode' => 'EUR'],
            'stateMachineState' => ['technicalName' => $state],
            'orderCustomer' => ['customerId' => $customerId, 'email' => $email, 'firstName' => 'Max', 'lastName' => 'Muster', 'company' => null, 'customer' => ['guest' => $guest]],
            'lineItems' => [['label' => 'Ware', 'quantity' => 2, 'totalPrice' => 119.0, 'payload' => ['productNumber' => 'SW10001']]],
        ];
    }

    private function connect(): ShopwareConnection {
        return ShopwareConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'shopware.example.test',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
            'import_confirmed_at' => now(),
        ]);
    }

    private function serve(): FakePluginHttp {
        return FakePluginHttp::fake([
            self::BASE . '/api/oauth/token' => FakePluginHttp::response(['token_type' => 'Bearer', 'expires_in' => 600, 'access_token' => 'sw-token']),
            self::BASE . '/api/_info/version' => FakePluginHttp::response(['version' => '6.6.4.0']),
            self::BASE . '/api/search-ids/order' => function (RequestInterface $request) {
                $criteria = JsonHelper::decode((string) $request->getBody());
                $wanted = (array) ($criteria['filter'][0]['value'] ?? []);
                $ids = array_values(array_filter(array_column($this->orders, 'id'), fn (string $id): bool => in_array($id, $wanted, true) && ! in_array($id, $this->gone, true)));

                return FakePluginHttp::response(['total' => count($ids), 'data' => $ids]);
            },
            self::BASE . '/api/search/order' => function (RequestInterface $request) {
                if ($this->failFirst !== []) {
                    return array_shift($this->failFirst);
                }
                $criteria = JsonHelper::decode((string) $request->getBody());
                $page = (int) ($criteria['page'] ?? 1);
                $limit = (int) ($criteria['limit'] ?? 25);

                return FakePluginHttp::response([
                    'total' => $this->reportedTotal ?? count($this->orders) - ($page > 1 ? $this->vanished : 0),
                    'data' => array_slice($this->orders, ($page - 1) * $limit, $limit),
                    'aggregations' => [],
                ]);
            },
        ]);
    }

    public function test_verify_signs_in_and_records_version(): void {
        $http = $this->serve();
        $this->actingAs($this->admin)->post(route('admin.shopware.verify'))->assertSessionHas('success');

        $connection = ShopwareConnection::query()->sole();
        $this->assertSame('shopware.example.test (Shopware 6.6.4.0)', $connection->source_name);
        $http->assertSent(static fn (RequestInterface $r): bool => str_ends_with($r->getUri()->getPath(), '/api/oauth/token')
            && str_contains((string) $r->getBody(), 'grant_type=client_credentials'));
        $http->assertSent(static fn (RequestInterface $r): bool => str_ends_with($r->getUri()->getPath(), '/api/search/order')
            && $r->getHeaderLine('Authorization') === 'Bearer sw-token');
    }

    public function test_import_maps_states_buyers_and_links(): void {
        $this->connect();
        $http = $this->serve();

        $counters = app(ShopwareOrderImportService::class)->run($this->organization);

        $this->assertSame(3, $counters['imported']);
        $first = ShopwareOrder::query()->where('order_number', 'SW-1')->sole();
        $this->assertSame(ShopOrderStatus::Processing, $first->status);
        $this->assertSame('in_progress', $first->remote_status_code);
        $this->assertSame('119.00', $first->gross_amount?->getAmount());
        $this->assertSame('100.00', $first->net_amount?->getAmount());
        $this->assertSame('customer:c' . str_repeat('1', 31), $first->buyer_external_id);
        $this->assertSame('SW10001', $first->items[0]['sku'] ?? null);
        $this->assertSame('guest:gast@example.test', ShopwareOrder::query()->where('order_number', 'SW-2')->sole()->buyer_external_id, 'Gastkonten zählen über die E-Mail.');
        $this->assertSame(self::BASE . '/admin#/sw/order/detail/' . $first->remote_order_id, app(ShopwareOrderImportService::class)->orderUrl($first));

        $tokens = array_filter($http->recorded(), static fn (array $call): bool => str_ends_with($call['request']->getUri()->getPath(), '/oauth/token'));
        $this->assertCount(1, $tokens, 'Ein Token für alle Seiten des Laufs.');
        $http->assertSent(static function (RequestInterface $r): bool {
            if (! str_ends_with($r->getUri()->getPath(), '/search/order')) {
                return false;
            }
            $criteria = JsonHelper::decode((string) $r->getBody());

            return ($criteria['page'] ?? null) === 2
                && ($criteria['sort'][0]['field'] ?? null) === 'id'
                && ($criteria['filter'][0]['operator'] ?? null) === 'or'
                && array_column($criteria['filter'][0]['queries'] ?? [], 'field') === ['updatedAt', 'createdAt', 'transactions.updatedAt']
                && ($criteria['associations']['transactions']['sort'][0]['field'] ?? null) === 'createdAt';
        });
    }

    public function test_refund_comes_from_the_latest_payment_transaction(): void {
        $this->connect();
        $this->serve();
        app(ShopwareOrderImportService::class)->run($this->organization);

        $this->orders[1]['transactions'] = [
            ['createdAt' => '2026-10-19T09:00:00.000+00:00', 'stateMachineState' => ['technicalName' => 'paid']],
            ['createdAt' => '2026-10-20T09:00:00.000+00:00', 'stateMachineState' => ['technicalName' => 'refunded']],
        ];
        $this->orders[0]['transactions'] = [['createdAt' => '2026-10-19T09:00:00.000+00:00', 'stateMachineState' => ['technicalName' => 'refunded_partially']]];
        app(ShopwareOrderImportService::class)->run($this->organization);

        $this->assertSame(ShopOrderStatus::Refunded, ShopwareOrder::query()->where('order_number', 'SW-2')->sole()->status, 'Bestellstatus „erledigt“, Zahlung erstattet.');
        $this->assertSame('completed', ShopwareOrder::query()->where('order_number', 'SW-2')->sole()->remote_status_code);
        $this->assertSame('119.00', ShopwareOrder::query()->where('order_number', 'SW-2')->sole()->refunded_amount?->getAmount(), 'Von Hand erstattet ohne Datensatz: voller Betrag.');
        $this->assertSame(ShopOrderStatus::Processing, ShopwareOrder::query()->where('order_number', 'SW-1')->sole()->status, 'Teilerstattung lässt den Bestellstatus.');
    }

    public function test_refunded_amount_comes_from_completed_refunds_and_deleted_orders_are_marked(): void {
        $refund = static fn (string $state, float $amount): array => ['stateMachineState' => ['technicalName' => $state], 'amount' => ['unitPrice' => $amount, 'quantity' => 1, 'totalPrice' => $amount]];
        $this->orders[0]['transactions'] = [[
            'createdAt' => '2026-10-19T09:00:00.000+00:00',
            'stateMachineState' => ['technicalName' => 'refunded_partially'],
            'captures' => [['refunds' => [$refund('completed', 20.0), $refund('open', 5.0), $refund('completed', 9.5)]]],
        ]];
        $this->orders[2]['transactions'] = [['createdAt' => '2026-10-19T09:00:00.000+00:00', 'stateMachineState' => ['technicalName' => 'refunded_partially'], 'captures' => []]];
        $this->connect();
        $this->serve();
        app(ShopwareOrderImportService::class)->run($this->organization);

        $this->assertSame('29.50', ShopwareOrder::query()->where('order_number', 'SW-1')->sole()->refunded_amount?->getAmount(), 'Nur abgeschlossene Erstattungen.');
        $this->assertSame('0.00', ShopwareOrder::query()->where('order_number', 'SW-2')->sole()->refunded_amount?->getAmount());
        $this->assertNull(ShopwareOrder::query()->where('order_number', 'SW-3')->sole()->refunded_amount, 'Teilweise erstattet ohne Datensatz: Betrag unbekannt.');

        $this->gone = [$this->orders[1]['id']];
        $http = $this->serve();
        $this->assertSame(1, app(ShopwareOrderImportService::class)->run($this->organization, true)['deleted']);
        $this->assertNotNull(ShopwareOrder::query()->where('order_number', 'SW-2')->sole()->remote_deleted_at);
        $http->assertSent(static function (RequestInterface $r): bool {
            if (! str_ends_with($r->getUri()->getPath(), '/search-ids/order')) {
                return false;
            }
            $criteria = JsonHelper::decode((string) $r->getBody());

            return ($criteria['filter'][0]['type'] ?? null) === 'equalsAny' && count($criteria['filter'][0]['value'] ?? []) === 3;
        });
    }

    public function test_a_shrinking_total_while_paging_is_incomplete(): void {
        $connection = $this->connect();
        $this->vanished = 1;
        $this->serve();

        app(ShopwareOrderImportService::class)->run($this->organization);

        $this->assertFalse($connection->refresh()->is_last_run_complete, 'Eine gelöschte Bestellung schiebt eine andere über die Seitengrenze.');
        $this->assertNull($connection->checkpoint_at);
    }

    public function test_the_read_only_search_is_retried_after_503(): void {
        $connection = $this->connect();
        $this->failFirst = [FakePluginHttp::response(['errors' => [['status' => '503']]], 503, ['Retry-After' => '0'])];
        $this->serve();

        $this->assertSame(3, app(ShopwareOrderImportService::class)->run($this->organization)['imported']);
        $this->assertTrue($connection->refresh()->is_last_run_complete);
    }

    public function test_short_listing_is_incomplete(): void {
        $connection = $this->connect();
        $this->reportedTotal = 5;
        $this->serve();

        app(ShopwareOrderImportService::class)->run($this->organization);

        $this->assertFalse($connection->refresh()->is_last_run_complete);
        $this->assertNull($connection->checkpoint_at);
        $this->assertSame(3, ShopwareOrder::query()->count(), 'Gelesenes bleibt erhalten.');
    }

    public function test_sync_command_imports_confirmed_connections(): void {
        $this->connect();
        $this->serve();

        $this->artisan('shopware:sync')->assertSuccessful();

        $this->assertSame(3, ShopwareOrder::query()->count());
    }
}
