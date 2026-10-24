<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\ShopOrders;

use App\Enums\Integration\{IntegrationInboxStatus, MarketplaceInboxStatus, ShopOrderStatus};
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Plugins\WooCommerce\Models\{WooCommerceConnection, WooCommerceOrder};
use App\Plugins\WooCommerce\Services\{WooCommerceOrderImportService, WooCommerceRetentionPolicies};
use App\Plugins\WooCommerce\WooCommercePlugin;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Shop-Importe am Beispiel WooCommerce (MVP-1325, 1328–1330): Verbindung mit
 * festgehaltener Instanz und HTTPS-Pflicht, Vorschau ohne Schreibwirkung,
 * Import erst nach Bestätigung, Status aus dem festen Schlüssel, Käufer nur
 * über die Marktplatz-Zuordnung, Aufholpunkt nur nach vollständigem Lesen,
 * Suche, Kunden-Cockpit und Aufbewahrung.
 */
class WooCommerceImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const BASE = 'https://shop.example.test';

    /** @var array<int, array<string, mixed>> */
    private array $orders = [];

    private ?int $failPage = null;

    /** Bestellungen, die ab Seite 2 im Papierkorb liegen und nicht mehr zählen. */
    private int $trashed = 0;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret(WooCommercePlugin::ID, ['base_url' => self::BASE . '/', 'consumer_key' => 'ck_test', 'consumer_secret' => 'cs_test']);
        $this->admin = $this->orgAdmin();
        app()->instance('currentOrganization', $this->organization);
        config(['plugins.woocommerce.page_size' => 2]);

        $this->order(101, 'processing', 5, 'erika@example.test', '119.00', '19.00');
        $this->order(102, 'completed', 0, 'gast@example.test', '50.00', '0.00');
        $this->order(103, 'cancelled', 0, 'storno@example.test', '10.00', '0.00');
    }

    private function order(int $id, string $status, int $customerId, string $email, string $total, string $tax, string $modified = '2026-10-20T08:00:00'): void {
        $this->orders[$id] = [
            'id' => $id,
            'number' => 'W-' . $id,
            'status' => $status,
            'currency' => 'EUR',
            'total' => $total,
            'total_tax' => $tax,
            'date_created_gmt' => '2026-10-19T09:00:00',
            'date_modified_gmt' => $modified,
            'customer_id' => $customerId,
            'billing' => ['first_name' => 'Erika', 'last_name' => 'Muster ' . $id, 'company' => '', 'email' => $email],
            'line_items' => [['name' => 'Ware ' . $id, 'sku' => 'SKU-' . $id, 'quantity' => 1, 'total' => $total]],
        ];
    }

    private function connect(bool $confirmed = true): WooCommerceConnection {
        return WooCommerceConnection::query()->forceCreate([
            'organization_id' => $this->organization->id,
            'source_id' => SelfHostedInstance::key(self::BASE),
            'source_name' => 'Musterladen',
            'source_url' => self::BASE,
            'status' => OAuthConnectionStatus::Active,
            'import_confirmed_at' => $confirmed ? now() : null,
        ]);
    }

    /** Kleiner WooCommerce-Shop: `modified_after`, `orderby=id`, Seiten mit `X-WP-TotalPages`. */
    private function serve(): FakePluginHttp {
        return FakePluginHttp::fake([
            self::BASE . '/wp-json/wc/v3/orders*' => function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);
                $page = (int) ($query['page'] ?? 1);
                if ($this->failPage === $page) {
                    return FakePluginHttp::response(['message' => 'boom'], 500);
                }
                $rows = array_values(array_filter($this->orders, static fn (array $o): bool => ! is_string($query['modified_after'] ?? null) || strcmp($o['date_modified_gmt'], $query['modified_after']) > 0));
                usort($rows, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
                $size = (int) ($query['per_page'] ?? 10);

                return FakePluginHttp::response(array_slice($rows, ($page - 1) * $size, $size), 200, [
                    'X-WP-Total' => (string) (count($rows) - ($page > 1 ? $this->trashed : 0)),
                    'X-WP-TotalPages' => (string) max(1, (int) ceil(count($rows) / $size)),
                ]);
            },
            // Wie WordPress: außerhalb der WooCommerce-Routen gilt ein Basic-Header als Anwendungspasswort.
            self::BASE . '/wp-json/' => static fn (RequestInterface $request) => $request->hasHeader('Authorization')
                ? FakePluginHttp::response(['code' => 'invalid_username', 'data' => ['status' => 401]], 401)
                : FakePluginHttp::response(['name' => 'Musterladen', 'url' => self::BASE]),
        ]);
    }

    /** @return array{imported: int, updated: int, unchanged: int, skipped: int, linked: int, staged: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(WooCommerceOrderImportService::class)->run($this->organization, $full);
    }

    public function test_verify_records_the_shop_and_https_is_required(): void {
        $this->serve();
        $this->actingAs($this->admin)->get(route('admin.woocommerce.index'))->assertOk()
            ->assertSee(route('admin.woocommerce.verify'), false)
            ->assertDontSee(route('admin.woocommerce.preview'), false);

        $this->post(route('admin.woocommerce.verify'))->assertSessionHas('success');
        $connection = WooCommerceConnection::query()->sole();
        $this->assertSame(SelfHostedInstance::key(self::BASE), $connection->source_id);
        $this->assertSame('Musterladen', $connection->source_name);
        $this->assertFalse($connection->isImportConfirmed());
        $this->get(route('admin.woocommerce.index'))->assertSee(route('admin.woocommerce.preview'), false);

        $this->pluginSecret(WooCommercePlugin::ID, ['base_url' => 'http://shop.example.test']);
        $this->post(route('admin.woocommerce.verify'))->assertSessionHas('error');
    }

    public function test_preview_writes_nothing_and_import_waits_for_confirmation(): void {
        $connection = $this->connect(confirmed: false);
        $this->serve();

        $this->actingAs($this->admin)->get(route('admin.woocommerce.preview'))->assertOk()
            ->assertSee('#W-101')
            ->assertSee(__('integration.shop_orders.preview.state.skipped'));
        $this->assertSame(0, WooCommerceOrder::query()->count());
        $this->assertSame(0, IntegrationInboxItem::query()->count());
        $this->assertSame(0, $this->sync()['imported'], 'Ohne Bestätigung läuft kein Import.');

        $this->post(route('admin.woocommerce.confirm'))->assertSessionHas('success');
        $this->assertTrue($connection->refresh()->isImportConfirmed());
    }

    public function test_import_reads_all_pages_maps_status_and_stages_buyers(): void {
        $this->freezeSecond();
        $connection = $this->connect();
        $http = $this->serve();

        $counters = $this->sync();

        $this->assertSame(2, $counters['imported']);
        $this->assertSame(1, $counters['skipped'], 'Neue Stornos nur mit Einstellung.');
        $this->assertSame(2, $counters['staged']);
        $first = WooCommerceOrder::query()->where('remote_order_id', '101')->sole();
        $this->assertSame(ShopOrderStatus::Processing, $first->status);
        $this->assertSame('W-101', $first->order_number);
        $this->assertSame('119.00', $first->gross_amount?->getAmount());
        $this->assertSame('100.00', $first->net_amount?->getAmount());
        $this->assertSame('customer:5', $first->buyer_external_id);
        $this->assertSame(MarketplaceInboxStatus::Open, $first->inbox_status);
        $this->assertSame('guest:gast@example.test', WooCommerceOrder::query()->where('remote_order_id', '102')->sole()->buyer_external_id);
        $this->assertSame(2, IntegrationInboxItem::query()->where('plugin_id', WooCommercePlugin::ID)->where('status', IntegrationInboxStatus::Open)->count());
        $this->assertSame(0, Customer::query()->count(), 'Kunden legt der Import nie an.');
        $http->assertSent(static fn (RequestInterface $r): bool => str_contains($r->getUri()->getQuery(), 'page=2') && str_contains($r->getUri()->getQuery(), 'orderby=id'));
        $this->assertTrue($connection->refresh()->is_last_run_complete);
        $this->assertSame(now()->toIso8601String(), $connection->checkpoint_at?->toIso8601String());

        $this->pluginSecret(WooCommercePlugin::ID, ['include_cancelled' => true]);
        $this->assertSame(1, $this->sync(full: true)['imported']);
        $this->assertSame(ShopOrderStatus::Cancelled, WooCommerceOrder::query()->where('remote_order_id', '103')->sole()->status);
    }

    public function test_later_runs_read_changes_and_a_reference_links_the_buyer(): void {
        $this->freezeSecond();
        $this->connect();
        $this->serve();
        $this->sync();

        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Erika Muster', 'created_by' => $this->admin->id]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => WooCommercePlugin::ID,
            'external_type' => 'customer',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => 'customer:5',
            'payload' => [],
            'synced_at' => now(),
        ]);
        $this->travel(20)->minutes();
        $this->orders[101]['status'] = 'completed';
        $this->orders[101]['date_modified_gmt'] = now()->utc()->format('Y-m-d\TH:i:s');
        $http = $this->serve();

        $counters = $this->sync();

        $this->assertSame(1, $counters['updated']);
        $this->assertSame(1, $counters['linked']);
        $order = WooCommerceOrder::query()->where('remote_order_id', '101')->sole();
        $this->assertSame(ShopOrderStatus::Completed, $order->status);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(MarketplaceInboxStatus::Linked, $order->inbox_status);
        $http->assertSent(static function (RequestInterface $r): bool {
            parse_str($r->getUri()->getQuery(), $q);

            return ($q['modified_after'] ?? null) === now()->subMinutes(30)->utc()->format('Y-m-d\TH:i:s');
        });
    }

    public function test_incomplete_read_keeps_the_checkpoint_and_auth_failure_is_reported(): void {
        $connection = $this->connect();
        $this->failPage = 2;
        $this->serve();

        $counters = $this->sync();

        $this->assertSame(2, $counters['imported'] + $counters['skipped']);
        $this->assertFalse($connection->refresh()->is_last_run_complete);
        $this->assertNull($connection->checkpoint_at);
        $this->assertNotNull($connection->last_error);

        FakePluginHttp::fake(['*' => FakePluginHttp::response(['code' => 'woocommerce_rest_cannot_view'], 401)]);
        $this->expectException(\App\Plugins\Support\PluginApiException::class);
        $this->sync();
    }

    public function test_a_shrinking_total_while_paging_is_incomplete(): void {
        $connection = $this->connect();
        $this->trashed = 1;
        $this->serve();

        $this->sync();

        $this->assertFalse($connection->refresh()->is_last_run_complete, 'Eine Bestellung im Papierkorb schiebt eine andere über die Seitengrenze.');
        $this->assertNull($connection->checkpoint_at);
    }

    public function test_page_search_cockpit_and_retention(): void {
        $this->freezeSecond();
        $this->connect();
        $this->serve();
        $this->sync();
        $customer = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Erika Muster', 'created_by' => $this->admin->id]);
        WooCommerceOrder::query()->where('remote_order_id', '101')->update(['customer_id' => $customer->id]);

        $this->actingAs($this->admin)->get(route('admin.woocommerce.index', ['q' => 'W-101']))->assertOk()
            ->assertSee(self::BASE . '/wp-admin/post.php?post=101&amp;action=edit', false)
            ->assertDontSee('#W-102');

        $domain = collect(app(ModuleRegistry::class)->extensions(SearchDomain::class))->map(fn (string $c) => app($c))->first(fn (SearchDomain $d): bool => $d->key() === 'woocommerce_orders');
        $this->assertNotNull($domain);
        $hits = $domain->search($this->admin, (int) $this->organization->id, 'gast@example', new SearchDomainFilters(), 10);
        $this->assertSame(['#W-102'], array_column($hits, 'title'));

        $this->get(route('customers.show', $customer))->assertOk()->assertSee('#W-101');

        $policy = app(WooCommerceRetentionPolicies::class)->policies()[0];
        $overdue = ($policy->overdueQuery)($this->organization, CarbonImmutable::now()->addYears(11))->get();
        $this->assertCount(2, $overdue);
        $this->assertNotNull($policy->purge);
        ($policy->purge)($overdue->first());
        $this->assertNull($overdue->first()->refresh()->buyer);
        $this->assertNotNull($overdue->first()->gross_amount, 'Beträge bleiben.');

        $this->post(route('admin.woocommerce.disconnect'))->assertSessionHas('success');
        $this->assertSame(2, WooCommerceOrder::query()->count());
    }

    public function test_sync_command_imports_confirmed_connections(): void {
        $this->connect();
        $this->serve();

        $this->artisan('woocommerce:sync')->assertSuccessful();

        $this->assertSame(2, WooCommerceOrder::query()->count());
    }

    public function test_sync_command_skips_unconfirmed_connections(): void {
        $this->connect(confirmed: false);
        $this->serve();

        $this->artisan('woocommerce:sync --full')->assertSuccessful();

        $this->assertSame(0, WooCommerceOrder::query()->count());
    }
}
