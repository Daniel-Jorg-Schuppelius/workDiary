<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Etsy;

use App\Models\{EtsyConnection, EtsyReceipt, EtsyWebhookDelivery, PluginSetting};
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Etsy\Jobs\EtsyWebhookIngestJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-496 (Phase 66): signierter Etsy-Webhook-Endpunkt. Der opake {token}
 * löst Org + Webhook-Secret auf; die Svix-HMAC-Signatur über
 * `"{id}.{ts}.{body}"` wird vor jeder Verarbeitung geprüft
 * (konstantzeitlich), Timestamp-Skew ≤ 5 min gegen Replay, Dedupe über den
 * Body-Hash. `shop_id` aus dem Payload ist nur Konsistenz-Check. Der
 * Webhook ist nur Impuls (Queue-Job lädt das Receipt über die fixe
 * Base-URL nach — nie die resource_url aus dem Payload).
 */
final class EtsyWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const RAW_KEY = 'test-webhook-key';

    private EtsyConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EtsyPlugin::ID,
            'enabled' => true,
            'settings' => [
                'keystring' => 'ks-1',
                'shared_secret' => 'sec-1',
                'webhook_secret' => 'whsec_' . base64_encode(self::RAW_KEY),
            ],
        ]);

        $this->connection = EtsyConnection::create([
            'organization_id' => $this->organization->id,
            'shop_id' => 77,
            'etsy_user_id' => 12345,
            'access_token' => '12345.tok',
            'status' => EtsyConnection::STATUS_ACTIVE,
            'webhook_token' => 'hook-123',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function postWebhook(array $payload, ?string $token = null, ?string $signature = null, ?int $timestamp = null, string $webhookId = 'msg-1'): TestResponse {
        $token ??= (string) $this->connection->webhook_token;
        $raw = (string) json_encode($payload);
        $timestamp ??= now()->getTimestamp();
        $signature ??= base64_encode(hash_hmac('sha256', $webhookId . '.' . $timestamp . '.' . $raw, self::RAW_KEY, true));

        return $this->call('POST', '/api/webhooks/etsy/' . $token, [], [], [], [
            'HTTP_webhook-id' => $webhookId,
            'HTTP_webhook-timestamp' => (string) $timestamp,
            'HTTP_webhook-signature' => 'v1,' . $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $raw);
    }

    /** @return array<string, mixed> */
    private function payload(int $receiptId = 900, int $shopId = 77): array {
        return [
            'event_type' => 'order.paid',
            'resource_url' => 'https://api.etsy.com/v3/application/shops/' . $shopId . '/receipts/' . $receiptId,
            'shop_id' => $shopId,
        ];
    }

    public function test_unknown_token_returns_404(): void {
        Queue::fake();

        $this->postWebhook($this->payload(), token: 'nope')->assertStatus(404);

        $this->assertSame(0, EtsyWebhookDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_invalid_signature_is_rejected_before_processing(): void {
        Queue::fake();

        $this->postWebhook($this->payload(), signature: base64_encode('deadbeef'))->assertStatus(401);

        $this->assertSame(0, EtsyWebhookDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_missing_secret_rejects_instead_of_fail_open(): void {
        Queue::fake();
        PluginSetting::query()->firstOrFail()
            ->update(['settings' => ['keystring' => 'ks-1', 'shared_secret' => 'sec-1']]);

        $this->postWebhook($this->payload())->assertStatus(401);
        Queue::assertNothingPushed();
    }

    public function test_stale_timestamp_is_rejected(): void {
        Queue::fake();

        $this->postWebhook($this->payload(), timestamp: now()->getTimestamp() - 1000)->assertStatus(401);

        $this->assertSame(0, EtsyWebhookDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_foreign_shop_id_is_ignored_after_signature(): void {
        Queue::fake();

        $this->postWebhook($this->payload(shopId: 99))->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame(0, EtsyWebhookDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_valid_delivery_is_logged_and_queued(): void {
        Queue::fake();

        $this->postWebhook($this->payload())->assertOk()->assertJson(['status' => 'queued']);

        $delivery = EtsyWebhookDelivery::query()->firstOrFail();
        $this->assertSame($this->organization->id, (int) $delivery->organization_id);
        $this->assertSame('order.paid', $delivery->event_type);
        $this->assertSame(900, (int) $delivery->receipt_id);
        Queue::assertPushed(EtsyWebhookIngestJob::class, fn(EtsyWebhookIngestJob $job): bool => $job->organizationId === $this->organization->id && $job->receiptId === 900);
    }

    public function test_duplicate_body_is_idempotent(): void {
        Queue::fake();
        $timestamp = now()->getTimestamp();

        $this->postWebhook($this->payload(), timestamp: $timestamp)->assertJson(['status' => 'queued']);
        $this->postWebhook($this->payload(), timestamp: $timestamp)->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, EtsyWebhookDelivery::query()->count());
        Queue::assertPushed(EtsyWebhookIngestJob::class, 1);
    }

    public function test_rotated_secret_second_signature_entry_matches(): void {
        Queue::fake();
        $raw = (string) json_encode($this->payload());
        $timestamp = now()->getTimestamp();
        $valid = base64_encode(hash_hmac('sha256', 'msg-1.' . $timestamp . '.' . $raw, self::RAW_KEY, true));

        // Svix listet bei Secret-Rotation mehrere Signaturen — ein Treffer genügt.
        $response = $this->call('POST', '/api/webhooks/etsy/hook-123', [], [], [], [
            'HTTP_webhook-id' => 'msg-1',
            'HTTP_webhook-timestamp' => (string) $timestamp,
            'HTTP_webhook-signature' => 'v1,' . base64_encode('wrong') . ' v1,' . $valid,
            'CONTENT_TYPE' => 'application/json',
        ], $raw);

        $response->assertOk()->assertJson(['status' => 'queued']);
    }

    public function test_ingest_job_mirrors_receipt_via_fixed_base_url(): void {
        // Job läuft inline (sync-Queue): das Receipt kommt über die fixe
        // Base-URL, nicht über die resource_url aus dem Payload.
        FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77/receipts/900' => FakePluginHttp::response([
                'receipt_id' => 900,
                'status' => 'paid',
                'is_paid' => true,
                'is_shipped' => false,
                'buyer_user_id' => null,
                'name' => 'Max Muster',
                'created_timestamp' => 1754200000,
                'updated_timestamp' => 1754280000,
                'grandtotal' => ['amount' => 4990, 'divisor' => 100, 'currency_code' => 'EUR'],
            ]),
        ]);

        $this->postWebhook($this->payload())->assertOk()->assertJson(['status' => 'queued']);

        $row = EtsyReceipt::query()->where('receipt_id', 900)->firstOrFail();
        $this->assertSame('paid', $row->status);
        $this->assertNotNull(EtsyWebhookDelivery::query()->firstOrFail()->processed_at);
    }
}
