<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Task, TodoistConnection, TodoistProjectLink, TodoistWebhookDelivery};
use App\Plugins\Todoist\Jobs\TodoistWebhookSyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 055, MVP-115: signierter Webhook-Endpunkt — HMAC-SHA256 über den
 * Raw-Body (hash_equals), Deduplizierung über die Delivery-ID VOR der
 * Verarbeitung, Org-Zuordnung erst nach der Signaturprüfung über
 * todoist_user_id; der Webhook ist nur Impuls für einen gezielten Abgleich.
 */
final class TodoistWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('plugins.todoist.client_id', 'cid');
        config()->set('plugins.todoist.client_secret', 'sec');
        // Aktive Verbindung mit bekanntem todoist_user_id — Grundlage der
        // Org-Zuordnung nach der Signaturprüfung (in der DB, nicht als Property).
        TodoistConnection::query()->create([
            'organization_id' => $this->organization->id,
            'todoist_user_id' => 'u-1',
            'access_token' => 'secret-token',
            'status' => TodoistConnection::STATUS_ACTIVE,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function postWebhook(array $payload, ?string $signature = null, string $deliveryId = 'd-1'): TestResponse {
        $raw = (string) json_encode($payload);
        $signature ??= base64_encode(hash_hmac('sha256', $raw, 'sec', true));

        return $this->call('POST', '/api/webhooks/todoist', [], [], [], [
            'HTTP_X-Todoist-Hmac-SHA256' => $signature,
            'HTTP_X-Todoist-Delivery-ID' => $deliveryId,
            'CONTENT_TYPE' => 'application/json',
        ], $raw);
    }

    /** @return array<string, mixed> */
    private function eventPayload(string $userId = 'u-1', string $projectId = 'tp-1'): array {
        return [
            'event_name' => 'item:updated',
            'user_id' => $userId,
            'event_data' => ['id' => 't-1', 'project_id' => $projectId],
        ];
    }

    public function test_invalid_signature_is_rejected_before_any_processing(): void {
        Queue::fake();

        $response = $this->postWebhook($this->eventPayload(), signature: base64_encode('falsch'));

        $response->assertStatus(401);
        $this->assertSame(0, TodoistWebhookDelivery::query()->count(), 'Nichts persistiert vor gültiger Signatur');
        Queue::assertNothingPushed();
    }

    public function test_valid_delivery_is_logged_and_queued(): void {
        Queue::fake();

        $response = $this->postWebhook($this->eventPayload());

        $response->assertOk()->assertJson(['status' => 'queued']);
        $delivery = TodoistWebhookDelivery::query()->firstOrFail();
        $this->assertSame('d-1', $delivery->delivery_id);
        $this->assertSame('item:updated', $delivery->event_name);
        $this->assertSame($this->organization->id, (int) $delivery->organization_id);
        Queue::assertPushed(TodoistWebhookSyncJob::class, fn (TodoistWebhookSyncJob $job): bool => $job->organizationId === $this->organization->id
            && $job->todoistProjectId === 'tp-1');
    }

    public function test_replay_with_same_delivery_id_is_idempotent(): void {
        Queue::fake();

        $this->postWebhook($this->eventPayload())->assertJson(['status' => 'queued']);
        $this->postWebhook($this->eventPayload())->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, TodoistWebhookDelivery::query()->count());
        Queue::assertPushed(TodoistWebhookSyncJob::class, 1);
    }

    public function test_unknown_todoist_user_is_logged_but_not_processed(): void {
        Queue::fake();

        $response = $this->postWebhook($this->eventPayload(userId: 'u-fremd'));

        $response->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertSame(1, TodoistWebhookDelivery::query()->count(), 'Signierte Zustellung bleibt protokolliert');
        $this->assertNull(TodoistWebhookDelivery::query()->firstOrFail()->organization_id);
        Queue::assertNothingPushed();
    }

    public function test_webhook_endpoint_is_rate_limited(): void {
        // Sessionloser Endpunkt: ungültige Signaturen zählen aufs Limit, damit
        // ein Angreifer den Endpunkt nicht ungedeckelt fluten kann (429 statt
        // endloser HMAC-Prüfungen). Polling heilt echte Verluste.
        for ($i = 0; $i < 120; $i++) {
            $this->postWebhook($this->eventPayload(), signature: base64_encode('falsch'), deliveryId: 'd-' . $i)
                ->assertStatus(401);
        }

        $this->postWebhook($this->eventPayload(), signature: base64_encode('falsch'), deliveryId: 'd-over')
            ->assertStatus(429);
    }

    public function test_webhook_impulse_triggers_targeted_sync(): void {
        TodoistProjectLink::query()->create([
            'organization_id' => $this->organization->id,
            'todoist_project_id' => 'tp-1',
            'todoist_project_name' => 'Sync-Projekt',
            'target_kind' => TodoistProjectLink::KIND_GLOBAL_KANBAN,
            'sync_mode' => TodoistProjectLink::MODE_BIDIRECTIONAL,
            'status' => TodoistProjectLink::STATUS_ACTIVE,
        ]);
        FakePluginHttp::fake([
            'https://api.todoist.com/api/v1/tasks*' => FakePluginHttp::response([
                'results' => [['id' => 't-1', 'content' => 'Per Webhook', 'priority' => 1]],
                'next_cursor' => null,
            ]),
        ]);

        // Sync-Queue: der Job läuft direkt — Webhook stößt den Abgleich nur an.
        $this->postWebhook($this->eventPayload())->assertOk()->assertJson(['status' => 'queued']);

        $this->assertSame('Per Webhook', Task::query()->firstOrFail()->title);
        $this->assertNotNull(TodoistWebhookDelivery::query()->firstOrFail()->processed_at);
    }
}
