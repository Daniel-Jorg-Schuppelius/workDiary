<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyReceiptImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Etsy;

use App\Models\{Customer, EtsyConnection, EtsyReceipt, ExternalReference, IntegrationInboxItem, Organization, PluginSetting, User};
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Etsy\Services\EtsyReceiptImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-495 (Phase 66): Etsy-Bestellimport Inbox-First — Spiegelzeilen mit
 * Money-Auflösung ({amount, divisor} → Decimal), deduplizierter Re-Import,
 * Käuferzuordnung nur über Referenz/eindeutigen Treffer (nie blind),
 * Gast-Käufe ohne Inbox-Fall, Aufholpunkt über min_last_modified.
 */
class EtsyReceiptImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private EtsyConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EtsyPlugin::ID,
            'enabled' => true,
            'settings' => ['keystring' => 'ks-1', 'shared_secret' => 'sec-1'],
        ]);

        $this->connection = EtsyConnection::create([
            'organization_id' => $this->organization->id,
            'shop_id' => 77,
            'shop_name' => 'Muster Shop',
            'etsy_user_id' => 12345,
            'access_token' => '12345.tok',
            'refresh_token' => 'ref-1',
            'status' => EtsyConnection::STATUS_ACTIVE,
            'webhook_token' => 'hook-123',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function receipt(int $id, ?int $buyerId, string $name, array $overrides = []): array {
        return array_merge([
            'receipt_id' => $id,
            'status' => 'paid',
            'is_paid' => true,
            'is_shipped' => false,
            'buyer_user_id' => $buyerId,
            'buyer_email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'name' => $name,
            'first_line' => 'Musterweg 1',
            'city' => 'Berlin',
            'zip' => '10115',
            'country_iso' => 'DE',
            'message_from_buyer' => 'Bitte klingeln',
            'created_timestamp' => 1754200000,
            'updated_timestamp' => 1754280000,
            'grandtotal' => ['amount' => 4990, 'divisor' => 100, 'currency_code' => 'EUR'],
            'total_shipping_cost' => ['amount' => 490, 'divisor' => 100, 'currency_code' => 'EUR'],
            'total_tax_cost' => ['amount' => 0, 'divisor' => 100, 'currency_code' => 'EUR'],
            'discount_amt' => ['amount' => 0, 'divisor' => 100, 'currency_code' => 'EUR'],
            'transactions' => [[
                'transaction_id' => $id * 10,
                'listing_id' => 111,
                'sku' => 'SKU-1',
                'title' => 'Holzschild',
                'quantity' => 1,
                'price' => ['amount' => 4990, 'divisor' => 100, 'currency_code' => 'EUR'],
            ]],
        ], $overrides);
    }

    /** @param array<int, array<string, mixed>> $receipts */
    private function fakeReceipts(array $receipts): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77/receipts?*' => FakePluginHttp::response([
                'count' => count($receipts),
                'results' => $receipts,
            ]),
        ]);
    }

    public function test_import_mirrors_receipts_and_stages_buyers(): void {
        $this->fakeReceipts([
            $this->receipt(900, 501, 'Max Muster'),
            $this->receipt(901, 502, 'Erika Beispiel'),
        ]);

        $result = app(EtsyReceiptImportService::class)->import($this->organization);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, $result['staged']);
        $this->assertFalse($result['truncated']);
        $this->assertSame(2, EtsyReceipt::query()->count());

        $row = EtsyReceipt::query()->where('receipt_id', 900)->firstOrFail();
        $this->assertSame('paid', $row->status);
        $this->assertTrue($row->was_paid);
        $this->assertSame('49.90', $row->total_gross?->getAmount());
        $this->assertSame('4.90', $row->total_shipping?->getAmount());
        $this->assertSame('Max Muster', data_get($row->buyer, 'name'));
        $this->assertSame('SKU-1', data_get($row->items, '0.sku'));
        $this->assertSame(EtsyReceipt::INBOX_OPEN, $row->inbox_status);
        $this->assertNull($row->customer_id);
        // Datensparsamkeit: Freitexte und PII-Duplikate nicht im Roh-Rest.
        $this->assertNull(data_get($row->raw, 'message_from_buyer'));
        $this->assertNull(data_get($row->raw, 'buyer_email'));

        // Kein Blind-Import: Käufer landen als Inbox-Vorschläge.
        $this->assertSame(2, IntegrationInboxItem::query()
            ->where('plugin_id', EtsyPlugin::ID)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->count());
        $this->assertSame(0, Customer::query()->count());
    }

    public function test_second_import_is_idempotent(): void {
        $this->fakeReceipts([$this->receipt(900, 501, 'Max Muster')]);
        app(EtsyReceiptImportService::class)->import($this->organization);

        $this->fakeReceipts([$this->receipt(900, 501, 'Max Muster')]);
        $result = app(EtsyReceiptImportService::class)->import($this->organization);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, EtsyReceipt::query()->count());
        // Dedupe der Drehscheibe: kein zweites Inbox-Item je Käufer.
        $this->assertSame(1, IntegrationInboxItem::query()->where('plugin_id', EtsyPlugin::ID)->count());
    }

    public function test_guest_orders_stay_open_without_inbox_noise(): void {
        $this->fakeReceipts([$this->receipt(902, null, 'Gast Kauf')]);

        $result = app(EtsyReceiptImportService::class)->import($this->organization);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['staged']);
        $row = EtsyReceipt::query()->where('receipt_id', 902)->firstOrFail();
        $this->assertNull($row->buyer_external_id);
        $this->assertSame(EtsyReceipt::INBOX_OPEN, $row->inbox_status);
        $this->assertSame(0, IntegrationInboxItem::query()->where('plugin_id', EtsyPlugin::ID)->count());
    }

    public function test_existing_reference_links_customer_and_backfills_open_rows(): void {
        $this->fakeReceipts([$this->receipt(900, 501, 'Max Muster')]);
        app(EtsyReceiptImportService::class)->import($this->organization);

        // Manuelle Zuordnung (Inbox) hinterlässt die Referenz.
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Max Muster',
            'created_by' => $this->admin->id,
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EtsyPlugin::ID,
            'external_type' => 'customer',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
            'external_id' => '501',
            'payload' => [],
            'synced_at' => now(),
        ]);

        // Zweite Runde: Wiederkäufer wird verlinkt, offene Zeile nachgezogen.
        $this->fakeReceipts([$this->receipt(903, 501, 'Max Muster')]);
        $result = app(EtsyReceiptImportService::class)->import($this->organization);

        $this->assertSame(1, $result['linked']);
        $this->assertSame(2, EtsyReceipt::query()->where('customer_id', $customer->id)->count());
        $this->assertSame(0, EtsyReceipt::query()->where('inbox_status', EtsyReceipt::INBOX_OPEN)->count());
    }

    public function test_checkpoint_uses_min_last_modified_and_advances(): void {
        $this->fakeReceipts([$this->receipt(900, 501, 'Max Muster')]);
        app(EtsyReceiptImportService::class)->import($this->organization);

        $this->assertSame(1754280000, $this->connection->refresh()->checkpoint('receipts_last_modified'));

        $fake = $this->fakeReceipts([]);
        app(EtsyReceiptImportService::class)->import($this->organization);

        // Aufholpunkt = Checkpoint minus Überlappung, aufsteigend nach updated.
        $fake->assertSent(fn(RequestInterface $r) => str_contains((string) $r->getUri(), 'min_last_modified=')
            && str_contains((string) $r->getUri(), 'sort_on=updated'));
    }

    public function test_page_budget_truncates_visibly(): void {
        config(['plugins.etsy.page_size' => 1]);
        // Über das Modell (encrypted:array-Cast), nie per Query-Update.
        PluginSetting::query()->firstOrFail()
            ->update(['settings' => ['keystring' => 'ks-1', 'shared_secret' => 'sec-1', 'sync_page_budget' => 2]]);

        FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77/receipts?*' => [
                FakePluginHttp::response(['count' => 3, 'results' => [$this->receipt(900, null, 'A B')]]),
                FakePluginHttp::response(['count' => 3, 'results' => [$this->receipt(901, null, 'C D')]]),
            ],
        ]);

        $result = app(EtsyReceiptImportService::class)->import($this->organization);

        $this->assertSame(2, $result['imported']);
        $this->assertTrue($result['truncated']);
        $this->assertTrue((bool) data_get($this->connection->refresh()->last_sync_counters, 'truncated'));
    }

    public function test_admin_page_lists_receipts(): void {
        $this->fakeReceipts([$this->receipt(900, 501, 'Max Muster')]);
        app(EtsyReceiptImportService::class)->import($this->organization);

        $response = $this->get(route('admin.etsy.index'));

        $response->assertOk();
        $response->assertSee('900');
        $response->assertSee('Max Muster');
        $response->assertSee(__('etsy.status.open_assignment'));
    }

    public function test_admin_page_denied_for_non_admin(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($member)->get(route('admin.etsy.index'))->assertForbidden();
    }

    public function test_settings_of_other_org_do_not_leak(): void {
        PluginSetting::query()->delete();
        $other = Organization::factory()->create();
        PluginSetting::create([
            'organization_id' => $other->id,
            'plugin_id' => EtsyPlugin::ID,
            'enabled' => true,
            'settings' => ['keystring' => 'foreign', 'shared_secret' => 'foreign'],
        ]);

        $fake = FakePluginHttp::fake([]);

        $this->expectException(\RuntimeException::class);
        try {
            app(EtsyReceiptImportService::class)->import($this->organization);
        } finally {
            $fake->assertNothingSent();
        }
    }
}
