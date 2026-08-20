<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\PluginSetting;
use App\Plugins\Lexoffice\Jobs\{SyncContactsJob, SyncVouchersJob};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Lexoffice-Webhook-Empfang (Audit 2026-08, Welle 1.3): URL-Token-Auth,
 * optionale RSA-Signaturprüfung, Persist-before-process-Dedup und
 * Impuls-Semantik (unique Sync-Jobs, nie Daten aus dem Payload).
 */
class LexofficeWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const SECRET = 'test-webhook-token-123456';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Queue::fake();
    }

    /** @param array<string, mixed> $extra */
    private function enableLexoffice(array $extra = []): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'api_key' => 'lex-key',
                'webhook_secret' => self::SECRET,
            ], $extra),
        ]);
    }

    /** @return array<string, string> */
    private function payload(string $eventType, string $resourceId = 'res-1'): array {
        return [
            'organizationId' => 'lex-org-1',
            'eventType' => $eventType,
            'resourceId' => $resourceId,
            'eventDate' => now()->toIso8601String(),
        ];
    }

    private function url(string $token = self::SECRET): string {
        return route('api.webhooks.lexoffice', ['organization' => $this->organization->id, 'token' => $token]);
    }

    public function test_voucher_event_queues_voucher_sync_and_records_delivery(): void {
        $this->enableLexoffice();

        $this->postJson($this->url(), $this->payload('voucher.changed'))
            ->assertOk()
            ->assertJson(['status' => 'queued']);

        Queue::assertPushed(SyncVouchersJob::class, 1);
        $this->assertDatabaseHas('lexoffice_webhook_deliveries', [
            'organization_id' => $this->organization->id,
            'event_type' => 'voucher.changed',
            'resource_id' => 'res-1',
        ]);
    }

    public function test_contact_and_payment_events_route_to_their_jobs(): void {
        $this->enableLexoffice();

        $this->postJson($this->url(), $this->payload('contact.changed', 'c-1'))->assertOk();
        $this->postJson($this->url(), $this->payload('payment.changed', 'p-1'))->assertOk();

        Queue::assertPushed(SyncContactsJob::class, 1);
        Queue::assertPushed(SyncVouchersJob::class, 1);
    }

    public function test_replay_is_deduplicated_before_processing(): void {
        $this->enableLexoffice();
        $payload = $this->payload('voucher.changed');

        $this->postJson($this->url(), $payload)->assertOk()->assertJson(['status' => 'queued']);
        $this->postJson($this->url(), $payload)->assertOk()->assertJson(['status' => 'duplicate']);

        Queue::assertPushed(SyncVouchersJob::class, 1);
        $this->assertSame(1, \App\Models\LexofficeWebhookDelivery::query()->count());
    }

    public function test_wrong_token_or_disabled_plugin_yields_404_without_traces(): void {
        $this->enableLexoffice();

        $this->postJson($this->url('falsches-token'), $this->payload('voucher.changed'))->assertNotFound();

        PluginSetting::query()->where('plugin_id', LexofficePlugin::ID)->update(['enabled' => false]);
        $this->postJson($this->url(), $this->payload('voucher.changed'))->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertSame(0, \App\Models\LexofficeWebhookDelivery::query()->count());
    }

    public function test_rsa_signature_is_enforced_when_public_key_is_configured(): void {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $details = openssl_pkey_get_details($key);
        $this->enableLexoffice(['webhook_public_key' => (string) $details['key']]);

        $payload = $this->payload('voucher.changed');
        $raw = (string) json_encode($payload);

        // Ohne/mit falscher Signatur: 403, nichts verarbeitet.
        $this->call('POST', $this->url(), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LXO_SIGNATURE' => base64_encode('falsch')], $raw)
            ->assertForbidden();
        Queue::assertNothingPushed();

        // Korrekt signiert: angenommen.
        openssl_sign($raw, $signature, $key, OPENSSL_ALGO_SHA512);
        $this->call('POST', $this->url(), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LXO_SIGNATURE' => base64_encode($signature)], $raw)
            ->assertOk();
        Queue::assertPushed(SyncVouchersJob::class, 1);
    }

    public function test_unknown_event_is_recorded_but_queues_nothing(): void {
        $this->enableLexoffice();

        $this->postJson($this->url(), $this->payload('invoice.created'))->assertOk();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('lexoffice_webhook_deliveries', ['event_type' => 'invoice.created']);
    }

    public function test_webhooks_command_generates_secret_and_creates_subscriptions(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'lex-key'],
        ]);

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/event-subscriptions' => FakePluginHttp::response(['content' => []], 200),
        ]);

        $this->artisan('lexoffice:webhooks', ['--organization' => $this->organization->id])
            ->assertExitCode(0);

        $setting = PluginSetting::query()
            ->where('organization_id', $this->organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->firstOrFail();
        $this->assertNotSame('', (string) ($setting->settings['webhook_secret'] ?? ''));
    }
}
