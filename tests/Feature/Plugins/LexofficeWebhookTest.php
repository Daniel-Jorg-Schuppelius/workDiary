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

use App\Models\Platform\PluginSetting;
use App\Plugins\Lexoffice\Exceptions\LexofficeApiException;
use App\Plugins\Lexoffice\Jobs\{SyncContactsJob, SyncVouchersJob};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
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
    use WithPluginSecrets;

    private const SECRET = 'test-webhook-token-123456';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
        Queue::fake();
    }

    /** @param array<string, mixed> $extra */
    private function enableLexoffice(array $extra = []): void {
        $this->pluginSecret('lexoffice', array_merge([
            'api_key' => 'lex-key',
            'webhook_secret' => self::SECRET,
        ], $extra));
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
        $this->assertSame(1, \App\Plugins\Lexoffice\Models\LexofficeWebhookDelivery::query()->count());
    }

    public function test_wrong_token_or_disabled_plugin_yields_404_without_traces(): void {
        $this->enableLexoffice();

        $this->postJson($this->url('falsches-token'), $this->payload('voucher.changed'))->assertNotFound();

        PluginSetting::query()->where('plugin_id', LexofficePlugin::ID)->update(['enabled' => false]);
        $this->postJson($this->url(), $this->payload('voucher.changed'))->assertNotFound();

        Queue::assertNothingPushed();
        $this->assertSame(0, \App\Plugins\Lexoffice\Models\LexofficeWebhookDelivery::query()->count());
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
        $this->pluginSecret('lexoffice', ['api_key' => 'lex-key']);

        $fake = $this->fakeSubscriptions();

        $this->artisan('lexoffice:webhooks', ['--organization' => $this->organization->id])
            ->assertExitCode(0);
        $this->assertCount(count(LexofficeWebhookService::EVENTS), array_filter($fake->recorded(), static fn (array $entry): bool => $entry['request']->getMethod() === 'POST'));

        $setting = PluginSetting::query()
            ->where('organization_id', $this->organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->firstOrFail();
        $this->assertNotSame('', (string) ($setting->settings['webhook_secret'] ?? ''));
    }

    /**
     * Abo-Liste, Anlegen (201) und Löschen (204) wie die Lexware-API.
     *
     * @param  list<array<string, string>>  $content
     */
    private function fakeSubscriptions(array $content = []): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://api.lexware.io/v1/event-subscriptions*' => static fn (RequestInterface $request) => match ($request->getMethod()) {
                'POST' => FakePluginHttp::response(['id' => 'sub-new', 'resourceUri' => 'https://api.lexware.io/v1/event-subscriptions/sub-new', 'version' => 0], 201),
                'DELETE' => FakePluginHttp::response(null, 204),
                default => FakePluginHttp::response(['content' => $content]),
            },
        ]);
    }

    public function test_ensure_subscriptions_sends_event_type_and_callback_for_missing_events_only(): void {
        $callback = 'https://workdiary.test/api/webhooks/lexoffice/7/token';
        $fake = $this->fakeSubscriptions([
            ['subscriptionId' => 'sub-1', 'eventType' => 'contact.created', 'callbackUrl' => $callback],
            ['subscriptionId' => 'sub-9', 'eventType' => 'contact.changed', 'callbackUrl' => 'https://fremd.test/hook'],
        ]);

        $result = (new LexofficeWebhookService($this->organization->id))->ensureSubscriptions($callback);

        $this->assertSame(['created' => 4, 'existing' => 1], $result);
        $posted = [];
        foreach ($fake->recorded() as $entry) {
            if ($entry['request']->getMethod() === 'POST') {
                $this->assertSame('https://api.lexware.io/v1/event-subscriptions', (string) $entry['request']->getUri());
                $posted[] = json_decode((string) $entry['request']->getBody(), true);
            }
        }
        $this->assertSame([
            ['eventType' => 'contact.changed', 'callbackUrl' => $callback],
            ['eventType' => 'voucher.created', 'callbackUrl' => $callback],
            ['eventType' => 'voucher.changed', 'callbackUrl' => $callback],
            ['eventType' => 'payment.changed', 'callbackUrl' => $callback],
        ], $posted);
    }

    public function test_remove_subscriptions_deletes_only_the_own_callback(): void {
        $callback = 'https://workdiary.test/api/webhooks/lexoffice/7/token';
        $fake = $this->fakeSubscriptions([
            ['subscriptionId' => 'sub-1', 'eventType' => 'contact.created', 'callbackUrl' => $callback],
            ['subscriptionId' => 'sub-2', 'eventType' => 'payment.changed', 'callbackUrl' => $callback],
            ['subscriptionId' => 'sub-9', 'eventType' => 'contact.changed', 'callbackUrl' => 'https://fremd.test/hook'],
        ]);

        $this->assertSame(2, (new LexofficeWebhookService($this->organization->id))->removeSubscriptions($callback));

        $deleted = array_values(array_map(
            static fn (array $entry): string => (string) $entry['request']->getUri(),
            array_filter($fake->recorded(), static fn (array $entry): bool => $entry['request']->getMethod() === 'DELETE'),
        ));
        $this->assertSame(['https://api.lexware.io/v1/event-subscriptions/sub-1', 'https://api.lexware.io/v1/event-subscriptions/sub-2'], $deleted);
    }

    /** Ein fremdes Abo mit einer Ereignisart, die das SDK-Enum nicht kennt, darf die Liste nicht brechen. */
    public function test_foreign_subscription_with_an_unknown_event_type_does_not_break_the_list(): void {
        $callback = 'https://workdiary.test/api/webhooks/lexoffice/7/token';
        $this->fakeSubscriptions([
            ['subscriptionId' => 'sub-7', 'eventType' => 'delivery-note.status.changed', 'callbackUrl' => 'https://fremd.test/hook'],
            ['subscriptionId' => 'sub-1', 'eventType' => 'contact.created', 'callbackUrl' => $callback],
        ]);

        $result = (new LexofficeWebhookService($this->organization->id))->ensureSubscriptions($callback);

        $this->assertSame(['created' => 4, 'existing' => 1], $result);
    }

    /**
     * EventSubscriptionsEndpoint::list(): `GET /event-subscriptions`. Eine Art, die das
     * SDK-Enum nicht kennt, liest es als null — das Abo bleibt mit leerem Typ in der Liste.
     */
    public function test_subscriptions_are_listed_through_the_sdk(): void {
        $fake = $this->fakeSubscriptions([
            ['subscriptionId' => 'sub-7', 'organizationId' => 'aa11bb22-cc33-4d44-8e55-ff6677889900', 'createdDate' => '2026-10-01T10:00:00.000+02:00', 'eventType' => 'delivery-note.status.changed', 'callbackUrl' => 'https://fremd.test/hook'],
            ['subscriptionId' => 'sub-8', 'eventType' => 'something.new', 'callbackUrl' => 'https://fremd.test/new'],
        ]);

        $this->assertSame([
            ['id' => 'sub-7', 'eventType' => 'delivery-note.status.changed', 'callbackUrl' => 'https://fremd.test/hook'],
            ['id' => 'sub-8', 'eventType' => '', 'callbackUrl' => 'https://fremd.test/new'],
        ], (new LexofficeWebhookService($this->organization->id))->subscriptions());
        $this->assertSame(['GET https://api.lexware.io/v1/event-subscriptions'], array_map(
            static fn (array $entry): string => $entry['request']->getMethod() . ' ' . $entry['request']->getUri(),
            $fake->recorded(),
        ));
    }

    public function test_subscription_errors_keep_the_http_status(): void {
        FakePluginHttp::fake(['https://api.lexware.io/v1/event-subscriptions' => FakePluginHttp::response(['message' => 'Unauthorized'], 401)]);

        try {
            (new LexofficeWebhookService($this->organization->id))->subscriptions();
            $this->fail('401 muss durchschlagen.');
        } catch (LexofficeApiException $e) {
            $this->assertSame('Lexoffice: Event-Subscriptions nicht abrufbar (HTTP 401).', $e->getMessage());
            $this->assertSame(401, $e->getCode());
        }
    }
}
