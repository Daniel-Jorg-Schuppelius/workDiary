<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudBackupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Models\Backup\BackupTargetConnection;
use App\Models\User;
use App\Plugins\Nextcloud\Api\NextcloudBackupClient;
use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeNextcloudTransportFactory;
use Tests\TestCase;

/**
 * Nextcloud-Backupziel (Feature 017 Phase 32, MVP-383): Kontobestätigung +
 * Quota über WebDAV, resumable Chunked-Upload mit Größen-Verifikation,
 * Listing/Löschen im eigenen Backupbereich (idempotent).
 */
class NextcloudBackupTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private BackupTargetConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->connection = BackupTargetConnection::factory()->create([
            'provider' => BackupProvider::Nextcloud,
            'name' => 'Nextcloud',
            'server_url' => 'https://nextcloud.test',
            'username' => 'alice',
            'access_token' => 'app-pw',
        ]);
    }

    /** @param list<Response> $responses */
    private function fake(array $responses): FakeNextcloudTransportFactory {
        $factory = new FakeNextcloudTransportFactory($responses);
        $this->app->instance(NextcloudTransportFactory::class, $factory);

        return $factory;
    }

    public function test_account_and_quota(): void {
        $this->fake([
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/', []),
            new Response(207, [], '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"><d:response>'
                . '<d:href>/remote.php/dav/files/alice/</d:href><d:propstat><d:prop>'
                . '<d:quota-available-bytes>600</d:quota-available-bytes>'
                . '<d:quota-used-bytes>400</d:quota-used-bytes>'
                . '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response></d:multistatus>'),
        ]);

        $client = new NextcloudBackupClient($this->connection->fresh());
        $this->assertSame('nextcloud.test|alice', $client->account()->externalId);
        $this->assertSame(['total' => 1000, 'used' => 400], $client->quota());
    }

    public function test_ensure_folder_returns_ref(): void {
        $this->fake([new Response(201)]);

        $ref = (new NextcloudBackupClient($this->connection->fresh()))->ensureFolder('/wd-backups-abc/');

        $this->assertSame('wd-backups-abc', $ref);
    }

    public function test_upload_part_uploads_resumable_and_verifies_size(): void {
        $path = tempnam(sys_get_temp_dir(), 'ncb');
        file_put_contents($path, 'CIPHER'); // 6 Bytes → ein Chunk (chunkSize 1024)

        $factory = $this->fake([
            new Response(201), // MKCOL „wd-backups-abc"
            new Response(201), // MKCOL „wd-backups-abc/gen-1"
            new Response(201), // MKCOL Upload-Session
            new Response(201), // PUT Chunk 0
            new Response(201), // MOVE .file → Ziel
            new Response(200, ['Content-Length' => '6']), // HEAD Verifikation
        ]);

        $ref = (new NextcloudBackupClient($this->connection->fresh()))
            ->uploadPart($path, 'wd-backups-abc/gen-1/part-0001.enc');
        @unlink($path);

        $this->assertSame('wd-backups-abc/gen-1/part-0001.enc', $ref);
        $methods = array_map(static fn ($e) => $e['request']->getMethod(), $factory->history);
        $this->assertSame(['MKCOL', 'MKCOL', 'MKCOL', 'PUT', 'MOVE', 'HEAD'], $methods);
    }

    public function test_list_objects_maps_children_and_tolerates_missing_prefix(): void {
        $this->fake([
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/wd-backups-abc/', [
                ['path' => '/remote.php/dav/files/alice/wd-backups-abc/gen-1/', 'dir' => true, 'fileid' => '201'],
            ]),
        ]);

        $objects = (new NextcloudBackupClient($this->connection->fresh()))->listObjects('wd-backups-abc');
        $this->assertCount(1, $objects);
        $this->assertSame('wd-backups-abc/gen-1', $objects[0]->ref);
        $this->assertSame('gen-1', $objects[0]->name);

        // Fehlender Prefix ⇒ leere Liste, kein Fehler.
        $this->fake([new Response(404)]);
        $this->assertSame([], (new NextcloudBackupClient($this->connection->fresh()))->listObjects('nope'));
    }

    public function test_delete_is_idempotent(): void {
        $this->fake([new Response(404)]);

        $this->assertTrue((new NextcloudBackupClient($this->connection->fresh()))->delete('wd-backups-abc/gen-1'));
    }

    public function test_connect_route_activates_target_for_platform_admin(): void {
        $admin = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->fake([
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/bob/', []), // account() ping
            new Response(207, [], '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"><d:response>'
                . '<d:href>/remote.php/dav/files/bob/</d:href><d:propstat><d:prop>'
                . '<d:quota-available-bytes>600</d:quota-available-bytes>'
                . '<d:quota-used-bytes>400</d:quota-used-bytes>'
                . '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response></d:multistatus>'),
            new Response(201), // ensureFolder MKCOL (Pseudonym = ein Segment)
        ]);

        $this->actingAs($admin)->post(route('admin.backup-targets.nextcloud.connect'), [
            'name' => 'NC-Backup',
            'server_url' => 'https://nextcloud.test',
            'username' => 'bob',
            'app_password' => 'secret-app-pw',
        ])->assertRedirect(route('admin.backup-targets.index'));

        $connection = BackupTargetConnection::query()
            ->where('provider', BackupProvider::Nextcloud->value)
            ->where('name', 'NC-Backup')
            ->firstOrFail();

        $this->assertSame(BackupTargetStatus::Active, $connection->status);
        $this->assertSame('https://nextcloud.test', $connection->server_url);
        $this->assertSame('nextcloud.test|bob', $connection->external_account_id);
        $this->assertSame(1000, $connection->quota_total);
    }

    public function test_connect_route_forbidden_for_org_admin(): void {
        $orgAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($orgAdmin)->post(route('admin.backup-targets.nextcloud.connect'), [
            'name' => 'NC-Backup',
            'server_url' => 'https://nextcloud.test',
            'username' => 'bob',
            'app_password' => 'pw',
        ])->assertForbidden();
    }
}
