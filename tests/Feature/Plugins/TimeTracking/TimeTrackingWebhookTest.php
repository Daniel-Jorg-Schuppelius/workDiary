<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeTrackingWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\TimeTracking;

use App\Models\{Organization, PluginSetting, TimeTrackingWebhookDelivery};
use App\Plugins\Clockify\ClockifyPlugin;
use App\Plugins\Support\TimeTracking\{TimeTrackingWebhookGate, WebhookImportJob};
use App\Plugins\Toggl\TogglPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Cache};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zeiterfassungs-Webhooks (Feature 124, MVP-613).
 *
 * Der Webhook WECKT nur: Er stößt denselben Lauf an, den auch der Scheduler
 * fährt. Geprüft wird deshalb vor allem, was NICHT passiert — kein Lauf ohne
 * gültige Signatur, kein zweiter Lauf bei Replay, kein Lauf je Einzelereignis.
 */
final class TimeTrackingWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Organization $orgB;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->orgB = Organization::factory()->create();
        Cache::flush();
    }

    private function settings(string $pluginId, Organization $organization, string $workspaceId, ?string $secret): void {
        PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => $pluginId,
            'enabled' => true,
            'settings' => array_filter([
                'workspace_id' => $workspaceId,
                'webhook_secret' => $secret,
            ], static fn ($value): bool => $value !== null),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function togglPost(array $payload, ?string $secret, ?string $signature = null): \Illuminate\Testing\TestResponse {
        $raw = (string) json_encode($payload);
        $signature ??= 'sha256=' . hash_hmac('sha256', $raw, (string) $secret);

        return $this->call(
            'POST',
            '/api/webhooks/toggl',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE_256' => $signature],
            $raw,
        );
    }

    /** @param array<string, mixed> $payload */
    private function clockifyPost(array $payload, ?string $signature): \Illuminate\Testing\TestResponse {
        return $this->call(
            'POST',
            '/api/webhooks/clockify',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_CLOCKIFY_SIGNATURE' => (string) $signature],
            (string) json_encode($payload),
        );
    }

    /** @return array<string, mixed> */
    private function togglPayload(string $eventId = 'evt-1'): array {
        return [
            'event_id' => $eventId,
            'metadata' => ['workspace_id' => '4711', 'action' => 'updated'],
            'payload' => ['id' => 99, 'workspace_id' => '4711'],
        ];
    }

    public function test_toggl_validation_ping_is_mirrored_without_signature(): void {
        Bus::fake();

        $this->togglPost(['validation_code' => 'abc123'], null, 'unsinn')
            ->assertOk()
            ->assertJson(['validation_code' => 'abc123']);

        Bus::assertNothingDispatched();
    }

    public function test_toggl_webhook_queues_the_regular_import(): void {
        Bus::fake();
        $this->settings(TogglPlugin::ID, $this->organization, '4711', 'geheim');

        $this->togglPost($this->togglPayload(), 'geheim')->assertOk()->assertJson(['status' => 'queued']);

        Bus::assertDispatched(WebhookImportJob::class);
        $this->assertDatabaseHas('time_tracking_webhook_deliveries', [
            'plugin_id' => TogglPlugin::ID,
            'delivery_id' => 'evt-1',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_toggl_webhook_rejects_a_wrong_signature(): void {
        Bus::fake();
        $this->settings(TogglPlugin::ID, $this->organization, '4711', 'geheim');

        $this->togglPost($this->togglPayload(), null, 'sha256=' . str_repeat('0', 64))->assertStatus(401);

        Bus::assertNothingDispatched();
        $this->assertSame(0, TimeTrackingWebhookDelivery::query()->count());
    }

    public function test_toggl_webhook_without_stored_secret_is_rejected(): void {
        Bus::fake();
        $this->settings(TogglPlugin::ID, $this->organization, '4711', null);

        $this->togglPost($this->togglPayload(), '')->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    public function test_unknown_workspace_is_ignored_without_telling(): void {
        Bus::fake();
        $this->settings(TogglPlugin::ID, $this->organization, '4711', 'geheim');

        $payload = $this->togglPayload();
        $payload['metadata']['workspace_id'] = '9999';

        $this->togglPost($payload, 'geheim')->assertOk()->assertJson(['status' => 'ignored']);

        Bus::assertNothingDispatched();
    }

    public function test_replay_of_the_same_delivery_runs_once(): void {
        Bus::fake();
        $this->settings(TogglPlugin::ID, $this->organization, '4711', 'geheim');

        $this->togglPost($this->togglPayload(), 'geheim')->assertJson(['status' => 'queued']);
        $this->togglPost($this->togglPayload(), 'geheim')->assertJson(['status' => 'duplicate']);

        Bus::assertDispatchedTimes(WebhookImportJob::class, 1);
        $this->assertSame(1, TimeTrackingWebhookDelivery::query()->count());
    }

    public function test_burst_of_events_is_debounced_to_one_run(): void {
        Bus::fake();
        $this->settings(TogglPlugin::ID, $this->organization, '4711', 'geheim');

        $this->togglPost($this->togglPayload('evt-1'), 'geheim')->assertJson(['status' => 'queued']);
        $this->togglPost($this->togglPayload('evt-2'), 'geheim')->assertJson(['status' => 'debounced']);
        $this->togglPost($this->togglPayload('evt-3'), 'geheim')->assertJson(['status' => 'debounced']);

        // Genau ein Lauf — sonst spränge die Quote, die der Webhook entlasten soll.
        Bus::assertDispatchedTimes(WebhookImportJob::class, 1);
        // Alle drei Zustellungen sind trotzdem protokolliert.
        $this->assertSame(3, TimeTrackingWebhookDelivery::query()->count());
    }

    public function test_the_workspace_decides_the_tenant(): void {
        Bus::fake();
        $this->settings(TogglPlugin::ID, $this->organization, '4711', 'geheim-a');
        $this->settings(TogglPlugin::ID, $this->orgB, '4712', 'geheim-b');

        $payload = $this->togglPayload();
        $payload['metadata']['workspace_id'] = '4712';

        $this->togglPost($payload, 'geheim-b')->assertJson(['status' => 'queued']);

        $this->assertDatabaseHas('time_tracking_webhook_deliveries', [
            'delivery_id' => 'evt-1',
            'organization_id' => $this->orgB->id,
        ]);
    }

    public function test_secret_of_another_tenant_does_not_open_the_door(): void {
        Bus::fake();
        $this->settings(TogglPlugin::ID, $this->organization, '4711', 'geheim-a');
        $this->settings(TogglPlugin::ID, $this->orgB, '4712', 'geheim-b');

        $payload = $this->togglPayload();
        $payload['metadata']['workspace_id'] = '4712';

        // Mit dem Geheimnis der EIGENEN Organisation auf den fremden Workspace.
        $this->togglPost($payload, 'geheim-a')->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    public function test_clockify_webhook_uses_the_shared_secret_header(): void {
        Bus::fake();
        $this->settings(ClockifyPlugin::ID, $this->organization, 'ws-abc', 'clockify-geheim');

        $this->clockifyPost(['id' => 'del-1', 'workspaceId' => 'ws-abc'], 'clockify-geheim')
            ->assertOk()
            ->assertJson(['status' => 'queued']);

        Bus::assertDispatched(WebhookImportJob::class);
    }

    public function test_clockify_webhook_rejects_a_wrong_secret(): void {
        Bus::fake();
        $this->settings(ClockifyPlugin::ID, $this->organization, 'ws-abc', 'clockify-geheim');

        $this->clockifyPost(['id' => 'del-1', 'workspaceId' => 'ws-abc'], 'falsch')->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    public function test_disabled_plugin_row_is_not_matched(): void {
        Bus::fake();
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => false,
            'settings' => ['workspace_id' => '4711', 'webhook_secret' => 'geheim'],
        ]);

        $this->togglPost($this->togglPayload(), 'geheim')->assertJson(['status' => 'ignored']);

        Bus::assertNothingDispatched();
    }

    public function test_gate_debounce_reopens_after_the_window(): void {
        $gate = app(TimeTrackingWebhookGate::class);

        $this->assertTrue($gate->shouldRun(TogglPlugin::ID, (int) $this->organization->id));
        $this->assertFalse($gate->shouldRun(TogglPlugin::ID, (int) $this->organization->id));

        // Andere Organisation, eigener Takt.
        $this->assertTrue($gate->shouldRun(TogglPlugin::ID, (int) $this->orgB->id));
    }
}
