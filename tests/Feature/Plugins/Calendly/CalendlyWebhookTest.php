<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Calendly;

use App\Models\{CalendlyConnection, CalendlyWebhookDelivery, CalendlyWebhookSubscription};
use App\Plugins\Calendly\Jobs\CalendlyIngestJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 095: signierter Calendly-Webhook-Endpunkt. Der opake {token} löst
 * Org + signing_key auf; die HMAC-SHA256-Signatur über `"<ts>.<body>"` wird vor
 * jeder Verarbeitung geprüft (konstantzeitlich), Timestamp-Skew ≤ 5 min gegen
 * Replay, Dedup über den Body-Hash. Der Webhook ist nur Impuls (Queue-Job).
 */
final class CalendlyWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const SIGNING_KEY = 'test-signing-key';

    private CalendlyWebhookSubscription $subscription;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $connection = CalendlyConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'tok',
            'status' => CalendlyConnection::STATUS_ACTIVE,
            'calendly_organization_uri' => 'https://api.calendly.com/organizations/o1',
            'calendly_user_uri' => 'https://api.calendly.com/users/u1',
        ]);

        $this->subscription = CalendlyWebhookSubscription::query()->create([
            'organization_id' => $this->organization->id,
            'calendly_connection_id' => $connection->id,
            'url_token' => 'tok-123',
            'signing_key' => self::SIGNING_KEY,
            'scope' => CalendlyWebhookSubscription::SCOPE_ORGANIZATION,
            'events' => ['invitee.created', 'invitee.canceled'],
            'status' => CalendlyWebhookSubscription::STATUS_ACTIVE,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function postWebhook(array $payload, ?string $token = null, ?string $signature = null, ?int $timestamp = null): TestResponse {
        $token ??= $this->subscription->url_token;
        $raw = (string) json_encode($payload);
        $timestamp ??= now()->getTimestamp();
        $signature ??= hash_hmac('sha256', $timestamp . '.' . $raw, self::SIGNING_KEY);

        return $this->call('POST', '/api/webhooks/calendly/' . $token, [], [], [], [
            'HTTP_Calendly-Webhook-Signature' => 't=' . $timestamp . ',v1=' . $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $raw);
    }

    /** @return array<string, mixed> */
    private function payload(string $inviteeUri = 'https://api.calendly.com/scheduled_events/e1/invitees/i1'): array {
        return [
            'event' => 'invitee.created',
            'payload' => [
                'uri' => $inviteeUri,
                'email' => 'jane@example.com',
                'name' => 'Jane Doe',
                'scheduled_event' => [
                    'uri' => 'https://api.calendly.com/scheduled_events/e1',
                    'start_time' => '2026-08-01T10:00:00.000000Z',
                    'end_time' => '2026-08-01T10:30:00.000000Z',
                ],
            ],
        ];
    }

    public function test_unknown_token_returns_404(): void {
        Queue::fake();

        $this->postWebhook($this->payload(), token: 'nope')->assertStatus(404);

        $this->assertSame(0, CalendlyWebhookDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_invalid_signature_is_rejected_before_processing(): void {
        Queue::fake();

        $this->postWebhook($this->payload(), signature: 'deadbeef')->assertStatus(401);

        $this->assertSame(0, CalendlyWebhookDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_stale_timestamp_is_rejected(): void {
        Queue::fake();

        $this->postWebhook($this->payload(), timestamp: now()->getTimestamp() - 1000)->assertStatus(401);

        $this->assertSame(0, CalendlyWebhookDelivery::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_valid_delivery_is_logged_and_queued(): void {
        Queue::fake();

        $this->postWebhook($this->payload())->assertOk()->assertJson(['status' => 'queued']);

        $delivery = CalendlyWebhookDelivery::query()->firstOrFail();
        $this->assertSame($this->organization->id, (int) $delivery->organization_id);
        $this->assertSame('invitee.created', $delivery->event_name);
        Queue::assertPushed(CalendlyIngestJob::class, fn (CalendlyIngestJob $job): bool => $job->organizationId === $this->organization->id);
    }

    public function test_duplicate_body_is_idempotent(): void {
        Queue::fake();
        $timestamp = now()->getTimestamp();

        $this->postWebhook($this->payload(), timestamp: $timestamp)->assertJson(['status' => 'queued']);
        $this->postWebhook($this->payload(), timestamp: $timestamp)->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, CalendlyWebhookDelivery::query()->count());
        Queue::assertPushed(CalendlyIngestJob::class, 1);
    }
}
