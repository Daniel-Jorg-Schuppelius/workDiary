<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudIntakeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeItemStatus, CloudIntakeProvider};
use App\Enums\User\Permission;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem};
use App\Models\User;
use App\Plugins\Contracts\{BackupTarget, DocumentIntakeSource, PluginCapability};
use App\Plugins\Nextcloud\Api\NextcloudIntakeClient;
use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use App\Plugins\Nextcloud\NextcloudPlugin;
use App\Plugins\Support\Intake\IntakeItem;
use App\Services\CloudIntake\StaleCheckpointException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeNextcloudTransportFactory;
use Tests\TestCase;

/**
 * Nextcloud-Dokumenteingang (Feature 080, MVP-382): budgetierter rekursiver
 * WebDAV-Scan → IntakeChangePage (fileid/ETag), Ordner-Paging, Tombstone-
 * Reconcile bei Zyklus-Abschluss, Stale-Checkpoint-Signal, Download-Stream.
 */
class NextcloudIntakeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private CloudDocumentConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'provider' => CloudIntakeProvider::Nextcloud,
            'name' => 'Nextcloud',
            'server_url' => 'https://nextcloud.test',
            'username' => 'alice',
            'access_token' => 'app-pw',
            'root_folder_path' => '/WorkDiary',
            'container_id' => 'files',
            'checkpoint' => null,
        ]);
    }

    /** @param list<Response> $responses */
    private function fake(array $responses): FakeNextcloudTransportFactory {
        $factory = new FakeNextcloudTransportFactory($responses);
        $this->app->instance(NextcloudTransportFactory::class, $factory);

        return $factory;
    }

    public function test_plugin_advertises_both_capabilities(): void {
        $plugin = new NextcloudPlugin();

        $this->assertContains(PluginCapability::DocumentIntake, $plugin->capabilities());
        $this->assertContains(PluginCapability::BackupTarget, $plugin->capabilities());
        $this->assertInstanceOf(DocumentIntakeSource::class, $plugin);
        $this->assertInstanceOf(BackupTarget::class, $plugin);
        $this->assertSame('nextcloud', CloudIntakeProvider::Nextcloud->pluginId());
    }

    public function test_recursive_scan_emits_files_from_root_and_subfolder(): void {
        $this->fake([
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/WorkDiary/', [
                ['path' => '/remote.php/dav/files/alice/WorkDiary/re-1.pdf', 'fileid' => '101', 'etag' => 'e1', 'mime' => 'application/pdf', 'size' => 1234],
                ['path' => '/remote.php/dav/files/alice/WorkDiary/Sub/', 'dir' => true, 'fileid' => '102', 'etag' => 'edir'],
            ]),
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/WorkDiary/Sub/', [
                ['path' => '/remote.php/dav/files/alice/WorkDiary/Sub/re-2.pdf', 'fileid' => '103', 'etag' => 'e2', 'mime' => 'application/pdf', 'size' => 22],
            ]),
        ]);

        $page = (new NextcloudIntakeClient($this->connection->fresh()))->changes(null);

        $this->assertFalse($page->hasMore);
        $paths = array_map(static fn (IntakeItem $i): string => $i->path, $page->items);
        sort($paths);
        $this->assertSame(['Sub/re-2.pdf', 're-1.pdf'], $paths);

        $first = collect($page->items)->firstWhere('path', 're-1.pdf');
        $this->assertSame('101', $first->itemId);
        $this->assertSame('e1', $first->revision);
        $this->assertSame('application/pdf', $first->mime);
    }

    public function test_folder_budget_pages_the_scan(): void {
        config(['plugins.nextcloud.scan_folder_budget' => 1]);

        $this->fake([
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/WorkDiary/', [
                ['path' => '/remote.php/dav/files/alice/WorkDiary/re-1.pdf', 'fileid' => '101', 'etag' => 'e1', 'size' => 1],
                ['path' => '/remote.php/dav/files/alice/WorkDiary/Sub/', 'dir' => true, 'fileid' => '102'],
            ]),
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/WorkDiary/Sub/', [
                ['path' => '/remote.php/dav/files/alice/WorkDiary/Sub/re-2.pdf', 'fileid' => '103', 'etag' => 'e2', 'size' => 2],
            ]),
        ]);
        $client = new NextcloudIntakeClient($this->connection->fresh());

        $page1 = $client->changes(null);
        $this->assertTrue($page1->hasMore);
        $this->assertCount(1, $page1->items);
        $this->assertSame('re-1.pdf', $page1->items[0]->path);

        $page2 = $client->changes($page1->checkpoint);
        $this->assertFalse($page2->hasMore);
        $this->assertCount(1, $page2->items);
        $this->assertSame('Sub/re-2.pdf', $page2->items[0]->path);
    }

    public function test_completed_cycle_reconciles_tombstones(): void {
        // Nachweis einer Datei, die im aktuellen Scan NICHT mehr auftaucht.
        CloudDocumentItem::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $this->connection->id,
            'provider' => CloudIntakeProvider::Nextcloud,
            'external_item_id' => '999',
            'status' => CloudIntakeItemStatus::Imported,
        ]);

        $this->fake([
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/WorkDiary/', [
                ['path' => '/remote.php/dav/files/alice/WorkDiary/re-1.pdf', 'fileid' => '101', 'etag' => 'e1', 'size' => 1],
            ]),
        ]);

        $page = (new NextcloudIntakeClient($this->connection->fresh()))->changes(null);

        $this->assertFalse($page->hasMore);
        $this->assertContains('999', $page->tombstones);
        $this->assertNotContains('101', $page->tombstones);
    }

    public function test_invalid_checkpoint_throws_stale(): void {
        $this->fake([]);

        $this->expectException(StaleCheckpointException::class);
        (new NextcloudIntakeClient($this->connection->fresh()))->changes('{not-json');
    }

    public function test_download_returns_stream(): void {
        $this->fake([new Response(200, [], 'PDF-INHALT')]);

        $item = new IntakeItem(itemId: '101', path: 'a.pdf', name: 'a.pdf', revision: 'e1', size: 9);
        $stream = (new NextcloudIntakeClient($this->connection->fresh()))->download($item);

        $this->assertSame('PDF-INHALT', (string) $stream);
    }

    public function test_account_confirms_identity_via_propfind(): void {
        $this->fake([FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/', [])]);

        $account = (new NextcloudIntakeClient($this->connection->fresh()))->account();

        $this->assertSame('nextcloud.test|alice', $account->externalId);
        $this->assertSame('alice @ nextcloud.test', $account->label);
    }

    public function test_connect_route_creates_connection_and_confirms_account(): void {
        $admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $admin->givePermissionTo(Permission::CloudIntakeConnectionManage->value);
        $this->fake([FakeNextcloudTransportFactory::folder('/remote.php/dav/files/bob/', [])]);

        $this->actingAs($admin)->post(route('admin.cloud-intake.nextcloud.connect'), [
            'name' => 'Meine Cloud',
            'server_url' => 'https://nextcloud.test',
            'username' => 'bob',
            'app_password' => 'secret-app-pw',
        ])->assertRedirect(route('admin.cloud-intake.index'));

        $connection = CloudDocumentConnection::query()
            ->where('provider', CloudIntakeProvider::Nextcloud->value)
            ->where('name', 'Meine Cloud')
            ->firstOrFail();

        $this->assertSame('https://nextcloud.test', $connection->server_url);
        $this->assertSame('bob', $connection->username);
        $this->assertSame('secret-app-pw', $connection->access_token);
        $this->assertSame('nextcloud.test|bob', $connection->external_account_id);
        $this->assertSame(CloudIntakeConnectionStatus::Draft, $connection->status);
    }

    public function test_connect_route_rejects_non_https_url(): void {
        $admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $admin->givePermissionTo(Permission::CloudIntakeConnectionManage->value);

        $this->actingAs($admin)
            ->from(route('admin.cloud-intake.index'))
            ->post(route('admin.cloud-intake.nextcloud.connect'), [
                'name' => 'X',
                'server_url' => 'http://nextcloud.test',
                'username' => 'bob',
                'app_password' => 'pw',
            ])
            ->assertSessionHasErrors('server_url');

        $this->assertDatabaseMissing('cloud_document_connections', [
            'provider' => CloudIntakeProvider::Nextcloud->value,
            'name' => 'X',
        ]);
    }
}
