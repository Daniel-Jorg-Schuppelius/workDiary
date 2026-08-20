<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveSubscriptionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\GoogleDrive\GoogleDrivePlugin;
use App\Plugins\GoogleDrive\Services\GoogleDriveSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Google-Drive-Push-Kanäle (Feature 080 P9; Audit 2026-08, W4.4). Der
 * Webhook-Empfänger existierte, die Kanal-ANLAGE fehlte — geprüft wird
 * Anlage, Nicht-Erneuerung bei langer Restlaufzeit, Ablösung eines alten
 * Kanals und das Abmelden beim Trennen.
 */
class GoogleDriveSubscriptionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CloudDocumentConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['plugins.google-drive.client_id' => 'cid', 'plugins.google-drive.client_secret' => 'sec']);

        $this->connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Google,
            'container_id' => 'my-drive',
            'external_account_id' => 'acc-1',
            'status' => CloudIntakeConnectionStatus::Active,
        ]);
    }

    public function test_plugin_implements_subscription_contract(): void {
        $this->assertInstanceOf(\App\Plugins\Contracts\DocumentIntakeSubscriptions::class, new GoogleDrivePlugin());
    }

    public function test_ensure_creates_watch_channel_and_persists_resource_id(): void {
        $fake = $this->fakeWatch();

        app(GoogleDriveSubscriptionService::class)->ensure($this->connection->fresh());

        $connection = $this->connection->fresh();
        $this->assertSame('chan-remote', $connection->subscription_id);
        $this->assertSame('res-42', $connection->subscription_resource_id);
        $this->assertNotNull($connection->subscription_expires_at);
        $this->assertTrue($connection->subscription_expires_at->isFuture());
        $this->assertNotSame('', (string) $connection->webhook_secret);

        $fake->assertSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/changes/watch'));
    }

    public function test_ensure_skips_when_channel_still_valid(): void {
        $this->connection->forceFill([
            'subscription_id' => 'chan-old',
            'subscription_resource_id' => 'res-old',
            'subscription_expires_at' => Carbon::now()->addHours(20),
        ])->save();

        $fake = $this->fakeWatch();

        app(GoogleDriveSubscriptionService::class)->ensure($this->connection->fresh());

        $this->assertSame('chan-old', $this->connection->fresh()->subscription_id);
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/changes/watch'));
    }

    public function test_ensure_stops_expiring_channel_before_creating_a_new_one(): void {
        $this->connection->forceFill([
            'subscription_id' => 'chan-old',
            'subscription_resource_id' => 'res-old',
            'subscription_expires_at' => Carbon::now()->addMinutes(10),
        ])->save();

        $fake = $this->fakeWatch();

        app(GoogleDriveSubscriptionService::class)->ensure($this->connection->fresh());

        $fake->assertSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/channels/stop'));
        $this->assertSame('chan-remote', $this->connection->fresh()->subscription_id);
    }

    public function test_unsubscribe_clears_channel_fields(): void {
        $this->connection->forceFill([
            'subscription_id' => 'chan-old',
            'subscription_resource_id' => 'res-old',
            'subscription_expires_at' => Carbon::now()->addHours(5),
        ])->save();

        $this->fakeWatch();

        app(GoogleDriveSubscriptionService::class)->unsubscribe($this->connection->fresh());

        $connection = $this->connection->fresh();
        $this->assertNull($connection->subscription_id);
        $this->assertNull($connection->subscription_resource_id);
        $this->assertNull($connection->subscription_expires_at);
    }

    public function test_command_reports_ensured_connections(): void {
        $this->fakeWatch();

        $this->artisan('google-drive:subscriptions')->assertExitCode(0);

        $this->assertSame('chan-remote', $this->connection->fresh()->subscription_id);
    }

    /** Nicht-Google-Verbindungen bleiben unangetastet (Provider-Leitplanke). */
    public function test_ensure_ignores_other_providers(): void {
        $dropbox = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Dropbox,
            'external_account_id' => 'acc-2',
        ]);
        $fake = $this->fakeWatch();

        app(GoogleDriveSubscriptionService::class)->ensure($dropbox);

        $this->assertNull($dropbox->fresh()->subscription_id);
        $fake->assertNotSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), '/changes/watch'));
    }

    private function fakeWatch(): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://www.googleapis.com/drive/v3/changes/startPageToken*' => FakePluginHttp::response(['startPageToken' => 'start-7']),
            'https://www.googleapis.com/drive/v3/changes/watch*' => FakePluginHttp::response([
                'id' => 'chan-remote',
                'resourceId' => 'res-42',
                'expiration' => (string) ((time() + 82800) * 1000),
            ]),
            'https://www.googleapis.com/drive/v3/channels/stop*' => FakePluginHttp::response([], 204),
        ]);
    }
}
