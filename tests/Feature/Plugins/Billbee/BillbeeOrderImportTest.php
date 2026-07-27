<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeOrderImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Billbee;

use App\Models\{BillbeeOrder, Customer, ExternalReference, IntegrationInboxItem, Organization, PluginSetting, User};
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Services\BillbeeOrderImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-433 (Phase 40): Billbee-Bestellimport Inbox-First — Spiegelzeilen mit
 * Kanalherkunft, deduplizierter Re-Import, Käuferzuordnung nur über
 * Referenz/eindeutigen Treffer (nie blind), Aufholpunkt über modifiedAtMin.
 */
class BillbeeOrderImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => BillbeePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'bb-key', 'username' => 'shopper', 'api_password' => 'bb-pass'],
        ]);
    }

    /** @return array<string, mixed> */
    private function order(int $billbeeId, string $buyerId, string $buyerName, string $channel = 'Amazon'): array {
        return [
            'BillBeeOrderId' => $billbeeId,
            'Id' => 'EXT-' . $billbeeId,
            'OrderNumber' => 'ORD-' . $billbeeId,
            'State' => 3,
            'CreatedAt' => '2030-04-01T10:00:00+02:00',
            'LastModifiedAt' => '2030-04-02T08:30:00+02:00',
            'Currency' => 'EUR',
            'TotalCost' => 49.9,
            'Seller' => ['Platform' => $channel, 'BillbeeShopName' => 'Shop'],
            'Buyer' => ['Id' => $buyerId, 'FullName' => $buyerName, 'Email' => strtolower(str_replace(' ', '.', $buyerName)) . '@example.test', 'Platform' => $channel],
            'OrderItems' => [['Quantity' => 1, 'TotalPrice' => 49.9]],
        ];
    }

    /** @param array<int, array<string, mixed>> $orders */
    private function fakeOrders(array $orders): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://app.billbee.io/api/v1/orders*' => FakePluginHttp::response([
                'Data' => $orders,
                'Paging' => ['Page' => 1, 'TotalPages' => 1, 'TotalRows' => count($orders)],
            ]),
        ]);
    }

    public function test_import_mirrors_orders_with_channel_and_stages_buyers(): void {
        $this->fakeOrders([
            $this->order(1001, 'b-1', 'Max Muster'),
            $this->order(1002, 'b-2', 'Erika Beispiel', 'eBay'),
        ]);

        $result = app(BillbeeOrderImportService::class)->import($this->organization);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, $result['staged']);
        $this->assertSame(2, BillbeeOrder::query()->count());

        $amazon = BillbeeOrder::query()->where('billbee_order_id', '1001')->firstOrFail();
        $this->assertSame('Amazon', $amazon->channel);
        $this->assertSame('ORD-1001', $amazon->order_number);
        $this->assertSame(BillbeeOrder::INBOX_OPEN, $amazon->inbox_status);
        $this->assertNull($amazon->customer_id);
        $this->assertSame('49.90', $amazon->total_gross?->getAmount());

        // Kein Blind-Import: Käufer landen als Inbox-Vorschläge.
        $this->assertSame(2, IntegrationInboxItem::query()
            ->where('plugin_id', BillbeePlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->count());
        $this->assertSame(0, Customer::query()->count());
    }

    public function test_second_import_is_idempotent(): void {
        $this->fakeOrders([$this->order(1001, 'b-1', 'Max Muster')]);
        app(BillbeeOrderImportService::class)->import($this->organization);

        $this->fakeOrders([$this->order(1001, 'b-1', 'Max Muster')]);
        $result = app(BillbeeOrderImportService::class)->import($this->organization);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, BillbeeOrder::query()->count());
        // Dedupe der Drehscheibe: kein zweites Inbox-Item je Käufer.
        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', BillbeePlugin::ID)->count());
    }

    public function test_existing_reference_links_customer_and_backfills_open_rows(): void {
        // Erste Runde: Bestellung bleibt offen.
        $this->fakeOrders([$this->order(1001, 'b-1', 'Max Muster')]);
        app(BillbeeOrderImportService::class)->import($this->organization);

        // Manuelle Zuordnung (Inbox) hinterlässt die Referenz.
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Max Muster',
            'created_by' => $this->admin->id,
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => BillbeePlugin::ID,
            'external_type' => 'customer',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => 'b-1',
            'payload' => [],
            'synced_at' => now(),
        ]);

        // Zweite Runde: Wiederkäufer wird verlinkt, offene Zeile nachgezogen.
        $this->fakeOrders([$this->order(1003, 'b-1', 'Max Muster')]);
        $result = app(BillbeeOrderImportService::class)->import($this->organization);

        $this->assertSame(1, $result['linked']);
        $this->assertSame(2, BillbeeOrder::query()->where('customer_id', $customer->id)->count());
        $this->assertSame(0, BillbeeOrder::query()->where('inbox_status', BillbeeOrder::INBOX_OPEN)->count());
    }

    public function test_checkpoint_uses_modified_at_min_with_overlap(): void {
        $this->fakeOrders([$this->order(1001, 'b-1', 'Max Muster')]);
        app(BillbeeOrderImportService::class)->import($this->organization);

        $fake = $this->fakeOrders([]);
        app(BillbeeOrderImportService::class)->import($this->organization);

        // Aufholpunkt = jüngste bekannte Änderung minus Überlappung.
        $fake->assertSent(fn(RequestInterface $r) => str_contains((string) $r->getUri(), 'modifiedAtMin='));
    }

    public function test_admin_page_lists_orders_with_channel_badge(): void {
        $this->fakeOrders([$this->order(1001, 'b-1', 'Max Muster')]);
        app(BillbeeOrderImportService::class)->import($this->organization);

        $response = $this->get(route('admin.billbee.index'));

        $response->assertOk();
        $response->assertSee('ORD-1001');
        $response->assertSee('Amazon');
        $response->assertSee(__('billbee.status.open_assignment'));
    }

    public function test_admin_page_denied_for_non_admin(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($member)->get(route('admin.billbee.index'))->assertForbidden();
    }

    public function test_settings_of_other_org_do_not_leak(): void {
        PluginSetting::query()->delete();
        $other = Organization::factory()->create();
        PluginSetting::create([
            'organization_id' => $other->id,
            'plugin_id' => BillbeePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'foreign', 'username' => 'foreign', 'api_password' => 'foreign'],
        ]);

        $fake = FakePluginHttp::fake([]);

        $this->expectException(\RuntimeException::class);
        try {
            app(BillbeeOrderImportService::class)->import($this->organization);
        } finally {
            $fake->assertNothingSent();
        }
    }
}
