<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyShipmentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Etsy;

use App\Models\{EtsyConnection, EtsyReceipt, IntegrationOutboxEntry, PluginSetting, User};
use App\Plugins\Etsy\EtsyPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-497 (Phase 66): Versand-Rückkanal über die Integrations-Outbox —
 * `receipt_shipped` meldet Tracking + Carrier idempotent an Etsy
 * (jede Bestellung höchstens einmal; Etsy benachrichtigt den Käufer),
 * unbekannte Carrier heilen sich über den dokumentierten Fallback `other`.
 */
final class EtsyShipmentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private EtsyReceipt $receipt;

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

        EtsyConnection::create([
            'organization_id' => $this->organization->id,
            'shop_id' => 77,
            'etsy_user_id' => 12345,
            'access_token' => '12345.tok',
            'status' => EtsyConnection::STATUS_ACTIVE,
            'webhook_token' => 'hook-123',
        ]);

        $this->receipt = EtsyReceipt::create([
            'organization_id' => $this->organization->id,
            'receipt_id' => 900,
            'status' => 'paid',
            'was_paid' => true,
            'was_shipped' => false,
            'currency' => 'EUR',
            'total_gross' => '49.90',
        ]);
    }

    public function test_ship_action_pushes_tracking_and_stamps_mirror(): void {
        $fake = FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77/receipts/900/tracking' => FakePluginHttp::response([]),
        ]);

        $this->post(route('admin.etsy.receipts.ship', $this->receipt), [
            'tracking_code' => '00340434161094000000',
            'carrier_name' => 'dhl',
        ])->assertRedirect();

        $fake->assertSent(fn(RequestInterface $r) => str_contains((string) $r->getUri(), '/receipts/900/tracking')
            && str_contains((string) $r->getBody(), '00340434161094000000'));

        $row = $this->receipt->refresh();
        $this->assertTrue($row->was_shipped);
        $this->assertNotNull($row->shipped_pushed_at);

        $entry = IntegrationOutboxEntry::query()->firstOrFail();
        $this->assertSame('receipt_shipped', $entry->operation);
        $this->assertSame('etsy:ship:900', $entry->idempotency_key);
    }

    public function test_second_ship_is_a_noop(): void {
        $fake = FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77/receipts/900/tracking' => FakePluginHttp::response([]),
        ]);

        $this->post(route('admin.etsy.receipts.ship', $this->receipt), ['tracking_code' => 'T-1', 'carrier_name' => 'dhl']);
        $this->post(route('admin.etsy.receipts.ship', $this->receipt), ['tracking_code' => 'T-1', 'carrier_name' => 'dhl'])
            ->assertSessionHas('success', __('etsy.flash.already_shipped'));

        // Genau EIN Tracking-Call, genau EIN Outbox-Eintrag.
        $fake->assertSentCount(1);
        $this->assertSame(1, IntegrationOutboxEntry::query()->count());
    }

    public function test_unknown_carrier_falls_back_to_other(): void {
        $fake = FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77/receipts/900/tracking' => [
                FakePluginHttp::response(['error' => 'carrier_name is invalid'], 400),
                FakePluginHttp::response([]),
            ],
        ]);

        $this->post(route('admin.etsy.receipts.ship', $this->receipt), [
            'tracking_code' => 'T-1',
            'carrier_name' => 'regionalkurier',
        ])->assertRedirect();

        $fake->assertSentCount(2);
        $fake->assertSent(fn(RequestInterface $r) => str_contains((string) $r->getBody(), '"carrier_name":"other"'));
        $this->assertTrue($this->receipt->refresh()->was_shipped);
    }

    public function test_ship_of_foreign_org_receipt_is_not_found(): void {
        $other = \App\Models\Organization::factory()->create();
        $foreign = EtsyReceipt::create([
            'organization_id' => $other->id,
            'receipt_id' => 999,
            'was_paid' => true,
            'was_shipped' => false,
        ]);

        $this->post(route('admin.etsy.receipts.ship', $foreign->id), ['tracking_code' => 'T-1'])
            ->assertNotFound();
    }
}
