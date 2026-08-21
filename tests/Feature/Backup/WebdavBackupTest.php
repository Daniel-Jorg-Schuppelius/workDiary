<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavBackupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Models\Backup\BackupTargetConnection;
use App\Models\User;
use App\Plugins\Webdav\Api\WebdavBackupClient;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Request, Response};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Generisches WebDAV-Backupziel (Feature 123, MVP-612): PROPFIND-Konto und
 * -Quota, Upload mit Größen-Verifikation, Listing/Löschen, und vor allem der
 * Selbsttest — ein Backupziel, das erst im Ernstfall auffällt, ist keins.
 */
class WebdavBackupTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @var list<Request> */
    private array $recorded = [];

    private BackupTargetConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->connection = BackupTargetConnection::factory()->create([
            'provider' => BackupProvider::Webdav,
            'name' => 'WebDAV',
            'server_url' => 'https://dav.example.com/dav/backup',
            'username' => 'alice',
            'access_token' => 'token',
        ]);
    }

    /** @param list<Response> $responses */
    private function client(array $responses): WebdavBackupClient {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(function (callable $handler) {
            return function (Request $request, array $options) use ($handler) {
                $this->recorded[] = $request;

                return $handler($request, $options);
            };
        });

        return new WebdavBackupClient($this->connection->fresh(), new GuzzleClient(['handler' => $stack]));
    }

    private function multiStatus(string $inner): Response {
        return new Response(207, [], '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:multistatus xmlns:d="DAV:">' . $inner . '</d:multistatus>');
    }

    public function test_account_uses_host_and_user(): void {
        $client = $this->client([$this->multiStatus('<d:response><d:href>/dav/backup/</d:href></d:response>')]);

        $account = $client->account();

        $this->assertSame('dav.example.com|alice', $account->externalId);
        $this->assertSame('PROPFIND', $this->recorded[0]->getMethod());
    }

    public function test_quota_is_summed_when_the_server_reports_it(): void {
        $client = $this->client([$this->multiStatus(
            '<d:response><d:href>/dav/backup/</d:href><d:propstat><d:prop>'
                . '<d:quota-available-bytes>600</d:quota-available-bytes>'
                . '<d:quota-used-bytes>400</d:quota-used-bytes>'
                . '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
        )]);

        $this->assertSame(['total' => 1000, 'used' => 400], $client->quota());
    }

    public function test_missing_quota_stays_unknown_instead_of_guessing(): void {
        $client = $this->client([$this->multiStatus('<d:response><d:href>/dav/backup/</d:href></d:response>')]);

        $this->assertSame(['total' => null, 'used' => null], $client->quota());
    }

    public function test_unlimited_quota_is_reported_as_unknown(): void {
        // RFC 4331: negative Werte bedeuten „unbegrenzt/unbekannt".
        $client = $this->client([$this->multiStatus(
            '<d:response><d:href>/dav/backup/</d:href><d:propstat><d:prop>'
                . '<d:quota-available-bytes>-3</d:quota-available-bytes>'
                . '<d:quota-used-bytes>400</d:quota-used-bytes>'
                . '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
        )]);

        $this->assertSame(['total' => null, 'used' => 400], $client->quota());
    }

    public function test_ensure_folder_creates_each_segment_idempotently(): void {
        $client = $this->client([new Response(201), new Response(405)]);

        $ref = $client->ensureFolder('/wd-backups-abc/2026/');

        $this->assertSame('wd-backups-abc/2026', $ref);
        $this->assertSame('MKCOL', $this->recorded[0]->getMethod());
        $this->assertStringEndsWith('/dav/backup/wd-backups-abc', (string) $this->recorded[0]->getUri());
        $this->assertStringEndsWith('/dav/backup/wd-backups-abc/2026', (string) $this->recorded[1]->getUri());
    }

    public function test_self_test_writes_reads_and_cleans_up(): void {
        // Winziger Fake-Server statt fester Antwortliste: Der Selbsttest
        // schreibt eine ZUFÄLLIGE Nutzlast und liest sie zurück — nur ein
        // Handler, der sich das Geschriebene merkt, prüft das ehrlich.
        $stored = [];
        $stack = HandlerStack::create(function (Request $request, array $options) use (&$stored) {
            $this->recorded[] = $request;
            $path = (string) $request->getUri()->getPath();

            $response = match ($request->getMethod()) {
                'MKCOL' => new Response(201),
                'PUT' => (static function () use ($request, $path, &$stored): Response {
                    $stored[$path] = (string) $request->getBody();

                    return new Response(201);
                })(),
                'GET' => isset($stored[$path]) ? new Response(200, [], $stored[$path]) : new Response(404),
                'DELETE' => (static function () use ($path, &$stored): Response {
                    unset($stored[$path]);

                    return new Response(204);
                })(),
                default => new Response(405),
            };

            return \GuzzleHttp\Promise\Create::promiseFor($response);
        });
        $client = new WebdavBackupClient($this->connection->fresh(), new GuzzleClient(['handler' => $stack]));

        $client->selfTest('wd-backups-abc');

        $methods = array_map(static fn (Request $r): string => $r->getMethod(), $this->recorded);
        $this->assertContains('PUT', $methods);
        $this->assertContains('GET', $methods);
        $this->assertContains('DELETE', $methods);
        // Aufräumen gehört zum Test: Was der Selbsttest anlegt, ist danach weg.
        $this->assertSame([], $stored);
    }

    public function test_self_test_fails_when_the_file_comes_back_changed(): void {
        $stack = HandlerStack::create(function (Request $request, array $options) {
            $response = match ($request->getMethod()) {
                'MKCOL', 'PUT' => new Response(201),
                'GET' => new Response(200, [], 'etwas-anderes'),
                default => new Response(204),
            };

            return \GuzzleHttp\Promise\Create::promiseFor($response);
        });
        $client = new WebdavBackupClient($this->connection->fresh(), new GuzzleClient(['handler' => $stack]));

        $this->expectException(RuntimeException::class);
        $client->selfTest('wd-backups-abc');
    }

    public function test_self_test_fails_when_the_file_cannot_be_written(): void {
        $client = $this->client([
            new Response(201),  // MKCOL Ordner
            new Response(201),  // MKCOL .wd-selftest
            new Response(507),  // PUT scheitert (Insufficient Storage)
        ]);

        $this->expectException(RuntimeException::class);
        $client->selfTest('wd-backups-abc');
    }

    public function test_upload_verifies_the_remote_size(): void {
        $path = (string) tempnam(sys_get_temp_dir(), 'wdb');
        file_put_contents($path, str_repeat('A', 128));

        $client = $this->client([
            new Response(201),                                  // MKCOL Elternordner
            new Response(201),                                  // PUT
            new Response(200, ['Content-Length' => '128']),     // HEAD
        ]);

        $ref = $client->upload($path, 'wd-backups-abc/snapshot.age');

        $this->assertSame('wd-backups-abc/snapshot.age', $ref);
        @unlink($path);
    }

    public function test_upload_rejects_a_truncated_remote_file(): void {
        $path = (string) tempnam(sys_get_temp_dir(), 'wdb');
        file_put_contents($path, str_repeat('A', 128));

        $client = $this->client([
            new Response(201),
            new Response(201),
            new Response(200, ['Content-Length' => '64']),
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $client->upload($path, 'wd-backups-abc/snapshot.age');
        } finally {
            @unlink($path);
        }
    }

    public function test_list_objects_skips_the_collection_itself_and_folders(): void {
        $client = $this->client([$this->multiStatus(
            '<d:response><d:href>/dav/backup/wd-backups-abc/</d:href>'
                . '<d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop></d:propstat></d:response>'
                . '<d:response><d:href>/dav/backup/wd-backups-abc/unter/</d:href>'
                . '<d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop></d:propstat></d:response>'
                . '<d:response><d:href>/dav/backup/wd-backups-abc/snapshot.age</d:href>'
                . '<d:propstat><d:prop><d:getcontentlength>512</d:getcontentlength>'
                . '<d:getlastmodified>Mon, 17 Aug 2026 10:00:00 GMT</d:getlastmodified>'
                . '<d:resourcetype/></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
        )]);

        $objects = $client->listObjects('wd-backups-abc');

        $this->assertCount(1, $objects);
        $this->assertSame('wd-backups-abc/snapshot.age', $objects[0]->ref);
        $this->assertSame('snapshot.age', $objects[0]->name);
        $this->assertSame(512, $objects[0]->size);
    }

    public function test_missing_prefix_lists_empty(): void {
        $client = $this->client([new Response(404)]);

        $this->assertSame([], $client->listObjects('wd-backups-abc'));
    }

    public function test_delete_is_idempotent(): void {
        $client = $this->client([new Response(404)]);

        $this->assertTrue($client->delete('wd-backups-abc/weg.age'));
    }

    public function test_plaintext_target_is_rejected(): void {
        $connection = BackupTargetConnection::factory()->create([
            'provider' => BackupProvider::Webdav,
            'server_url' => 'http://dav.example.com/dav',
            'username' => 'alice',
            'access_token' => 'token',
        ]);

        $this->expectException(RuntimeException::class);
        new WebdavBackupClient($connection, new GuzzleClient());
    }

    public function test_private_target_is_rejected_without_opt_in(): void {
        $connection = BackupTargetConnection::factory()->create([
            'provider' => BackupProvider::Webdav,
            'server_url' => 'https://10.0.0.5/dav',
            'username' => 'alice',
            'access_token' => 'token',
        ]);

        $this->expectException(RuntimeException::class);
        new WebdavBackupClient($connection, new GuzzleClient());
    }

    public function test_connect_dialog_and_validation(): void {
        $admin = User::factory()->create(['organization_id' => $this->organization->id, 'is_platform_admin' => true]);

        $this->actingAs($admin)->get(route('admin.backup-targets.webdav.connect-form'))->assertOk();

        $this->actingAs($admin)->post(route('admin.backup-targets.webdav.connect'), [
            'name' => 'Eigener Server',
            'server_url' => 'http://dav.example.com/dav',
            'username' => 'alice',
            'password' => 'token',
        ])->assertSessionHasErrors('server_url');

        $this->assertSame(0, BackupTargetConnection::query()
            ->where('provider', BackupProvider::Webdav->value)
            ->where('status', BackupTargetStatus::Active->value)
            ->count());
    }
}
