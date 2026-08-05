<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeProvider;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Msgraph\Api\MsgraphIntakeClient;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Support\Intake\IntakeItem;
use App\Services\CloudIntake\StaleCheckpointException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Microsoft-Graph-Intake (Feature 080, MVP-354): Delta → IntakeChangePage
 * (nextLink ⇒ hasMore, deltaLink ⇒ Abschluss-Checkpoint, deleted-Facette ⇒
 * ID-Tombstone), 410 ⇒ Vollabgleich-Signal, Download, Capability.
 */
class MsgraphIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CloudDocumentConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config(['plugins.msgraph.client_id' => 'cid', 'plugins.msgraph.client_secret' => 'sec']);

        $this->connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Microsoft,
            'container_id' => 'drive-1',
            'root_folder_id' => 'root-item',
            'root_folder_path' => 'WorkDiary',
        ]);
    }

    public function test_plugin_advertises_document_intake_capability(): void {
        $plugin = new MsgraphPlugin();

        $this->assertContains(\App\Plugins\Contracts\PluginCapability::DocumentIntake, $plugin->capabilities());
        $this->assertInstanceOf(\App\Plugins\Contracts\DocumentIntakeSource::class, $plugin);
    }

    public function test_delta_maps_files_deleted_and_next_link(): void {
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/drives/drive-1/items/root-item/delta*' => FakePluginHttp::response([
                'value' => [
                    ['id' => 'item-1', 'name' => 're-1.pdf', 'size' => 555, 'eTag' => 'etag-1',
                        'lastModifiedDateTime' => '2026-07-14T10:00:00Z',
                        'file' => ['mimeType' => 'application/pdf', 'hashes' => ['quickXorHash' => 'qx']],
                        'parentReference' => ['id' => 'parent-1', 'path' => '/drives/drive-1/root:/WorkDiary/Eingangsrechnungen']],
                    ['id' => 'folder-1', 'name' => 'Ordner', 'folder' => ['childCount' => 1],
                        'parentReference' => ['path' => '/drives/drive-1/root:/WorkDiary']],
                    ['id' => 'item-gone', 'deleted' => ['state' => 'deleted']],
                ],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/drives/drive-1/items/root-item/delta?token=page2',
            ]),
        ]);

        $page = (new MsgraphIntakeClient($this->connection->fresh()))->changes(null);

        $this->assertCount(1, $page->items);
        $item = $page->items[0];
        $this->assertSame('item-1', $item->itemId);
        $this->assertSame('Eingangsrechnungen/re-1.pdf', $item->path);
        $this->assertSame('etag-1', $item->revision);
        $this->assertSame('application/pdf', $item->mime);
        $this->assertSame(['item-gone'], $page->tombstones);
        $this->assertTrue($page->hasMore);
        $this->assertStringContainsString('token=page2', $page->checkpoint);
    }

    public function test_checkpoint_url_is_called_directly_and_delta_link_ends_paging(): void {
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/drives/drive-1/items/root-item/delta?token=page2' => FakePluginHttp::response([
                'value' => [],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/drives/drive-1/items/root-item/delta?token=final',
            ]),
        ]);

        $page = (new MsgraphIntakeClient($this->connection->fresh()))
            ->changes('https://graph.microsoft.com/v1.0/drives/drive-1/items/root-item/delta?token=page2');

        $this->assertFalse($page->hasMore);
        $this->assertStringContainsString('token=final', $page->checkpoint);
        $fake->assertSent(fn ($request) => str_contains((string) $request->getUri(), 'token=page2'));
    }

    public function test_gone_delta_token_throws_stale_checkpoint_exception(): void {
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/*' => FakePluginHttp::response(['error' => ['code' => 'resyncRequired']], 410),
        ]);

        $this->expectException(StaleCheckpointException::class);
        (new MsgraphIntakeClient($this->connection->fresh()))->changes('https://graph.microsoft.com/v1.0/drives/drive-1/items/root-item/delta?token=alt');
    }

    public function test_download_streams_item_content(): void {
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/drives/drive-1/items/item-1/content' => FakePluginHttp::response('GRAPH-INHALT'),
        ]);

        $item = new IntakeItem(itemId: 'item-1', path: 'a.pdf', name: 'a.pdf', revision: 'etag-1', size: 12);
        $stream = (new MsgraphIntakeClient($this->connection->fresh()))->download($item);

        $this->assertSame('GRAPH-INHALT', (string) $stream);
        $fake->assertSent(fn ($request) => str_contains((string) $request->getUri(), '/items/item-1/content'));
    }

    public function test_containers_lists_personal_drives(): void {
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/drives' => FakePluginHttp::response([
                'value' => [
                    ['id' => 'drive-1', 'name' => 'OneDrive', 'driveType' => 'business'],
                    ['id' => 'drive-2', 'name' => 'Dokumente', 'driveType' => 'documentLibrary'],
                ],
            ]),
        ]);

        $containers = (new MsgraphIntakeClient($this->connection->fresh()))->containers();

        $this->assertCount(2, $containers);
        $this->assertSame('drive-1', $containers[0]->id);
        $this->assertSame('documentLibrary', $containers[1]->kind);
    }

    public function test_containers_with_search_adds_sharepoint_site_libraries(): void {
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/drives' => FakePluginHttp::response([
                'value' => [['id' => 'drive-1', 'name' => 'OneDrive', 'driveType' => 'business']],
            ]),
            'https://graph.microsoft.com/v1.0/sites?search=Projekt' => FakePluginHttp::response([
                'value' => [['id' => 'site-9', 'displayName' => 'Projekte']],
            ]),
            'https://graph.microsoft.com/v1.0/sites/site-9/drives' => FakePluginHttp::response([
                'value' => [
                    // drive-1 taucht auch als Site-Bibliothek auf → dedupliziert.
                    ['id' => 'drive-1', 'name' => 'Dublette', 'driveType' => 'documentLibrary'],
                    ['id' => 'lib-1', 'name' => 'Freigaben', 'driveType' => 'documentLibrary'],
                ],
            ]),
        ]);

        $containers = (new MsgraphIntakeClient($this->connection->fresh()))->containers('Projekt');

        $this->assertCount(2, $containers);
        $this->assertSame('drive-1', $containers[0]->id);
        $this->assertSame('lib-1', $containers[1]->id);
        $this->assertSame('Projekte — Freigaben', $containers[1]->label);
    }

    public function test_subscription_is_created_once_and_skipped_while_valid(): void {
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/subscriptions' => FakePluginHttp::response([
                'id' => 'sub-1', 'expirationDateTime' => now()->addDays(29)->toIso8601String(),
            ], 201),
        ]);

        app(\App\Plugins\Msgraph\Services\MsgraphSubscriptionService::class)->ensure($this->connection->fresh());

        $fresh = $this->connection->fresh();
        $this->assertSame('sub-1', $fresh->subscription_id);
        $this->assertNotNull($fresh->subscription_expires_at);
        $this->assertNotSame('', (string) $fresh->webhook_secret);

        // Lange gültig → kein weiterer API-Aufruf.
        $idle = FakePluginHttp::fake();
        app(\App\Plugins\Msgraph\Services\MsgraphSubscriptionService::class)->ensure($this->connection->fresh());
        $idle->assertNothingSent();
    }

    public function test_expiring_subscription_is_renewed_and_recreated_on_404(): void {
        $this->connection->forceFill([
            'subscription_id' => 'sub-alt',
            'subscription_expires_at' => now()->addDay(),
            'webhook_secret' => 'geheimes-clientstate',
        ])->save();

        // PATCH liefert 404 (Graph hat aufgeräumt) → Neuanlage mit ALTEM clientState.
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/subscriptions/sub-alt' => FakePluginHttp::response(['error' => ['code' => 'ResourceNotFound']], 404),
            'https://graph.microsoft.com/v1.0/subscriptions' => FakePluginHttp::response([
                'id' => 'sub-neu', 'expirationDateTime' => now()->addDays(29)->toIso8601String(),
            ], 201),
        ]);

        app(\App\Plugins\Msgraph\Services\MsgraphSubscriptionService::class)->ensure($this->connection->fresh());

        $fresh = $this->connection->fresh();
        $this->assertSame('sub-neu', $fresh->subscription_id);
        $this->assertSame('geheimes-clientstate', $fresh->webhook_secret);
        $fake->assertSent(function ($request): bool {
            if (! str_ends_with((string) $request->getUri(), '/subscriptions')) {
                return false;
            }
            /** @var array{clientState?: string, resource?: string, changeType?: string} $payload */
            $payload = (array) json_decode((string) $request->getBody(), true);

            return ($payload['clientState'] ?? null) === 'geheimes-clientstate'
                && ($payload['resource'] ?? null) === '/drives/drive-1/root'
                && ($payload['changeType'] ?? null) === 'updated';
        });
    }

    public function test_subscriptions_command_covers_runnable_connections(): void {
        $this->connection->forceFill([
            'external_account_id' => 'acc-1',
            'status' => \App\Enums\CloudIntake\CloudIntakeConnectionStatus::Active,
        ])->save();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/subscriptions' => FakePluginHttp::response([
                'id' => 'sub-cmd', 'expirationDateTime' => now()->addDays(29)->toIso8601String(),
            ], 201),
        ]);

        $this->artisan('msgraph:subscriptions')->assertExitCode(0);
        $this->assertSame('sub-cmd', $this->connection->fresh()->subscription_id);
    }

    public function test_containers_without_search_skips_site_lookup(): void {
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/drives' => FakePluginHttp::response([
                'value' => [['id' => 'drive-1', 'name' => 'OneDrive', 'driveType' => 'business']],
            ]),
        ]);

        (new MsgraphIntakeClient($this->connection->fresh()))->containers();

        $fake->assertNotSent(fn ($request) => str_contains((string) $request->getUri(), '/sites'));
    }
}
