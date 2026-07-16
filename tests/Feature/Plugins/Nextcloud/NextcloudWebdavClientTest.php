<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudWebdavClientTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Nextcloud;

use App\Plugins\Nextcloud\Api\{NextcloudNotFoundException, NextcloudWebdavClient};
use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use RuntimeException;
use Tests\Support\FakeNextcloudTransportFactory;
use Tests\TestCase;

/**
 * WebDAV-Transport (Feature 080 MVP-382 / Feature 017 MVP-383): PROPFIND-
 * Parsing (oc:fileid/ETag/Größe/Typ, Selbst-Eintrag überspringen), Quota,
 * SSRF-/HTTPS-Guard, resumable Chunked-Upload v2 mit Größen-Verifikation und
 * idempotentes Löschen.
 */
class NextcloudWebdavClientTest extends TestCase {
    /** @var list<array{request: \Psr\Http\Message\RequestInterface, response: mixed, error: mixed, options: array<mixed>}> */
    private array $history = [];

    /** @param  list<Response>  $responses */
    private function client(array $responses, int $chunkSize = 4): NextcloudWebdavClient {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new NextcloudWebdavClient(
            new Client(['handler' => $stack]),
            'https://nextcloud.test',
            'alice',
            'app-pw',
            allowPrivateTargets: true,
            chunkSize: $chunkSize,
        );
    }

    public function test_https_is_required(): void {
        $this->expectException(RuntimeException::class);
        new NextcloudWebdavClient(new Client(), 'http://nextcloud.test', 'alice', 'pw', allowPrivateTargets: true);
    }

    public function test_private_target_is_rejected_without_override(): void {
        $this->expectException(RuntimeException::class);
        new NextcloudWebdavClient(new Client(), 'https://127.0.0.1', 'alice', 'pw', allowPrivateTargets: false);
    }

    public function test_list_children_parses_files_and_skips_self(): void {
        $client = $this->client([
            FakeNextcloudTransportFactory::folder('/remote.php/dav/files/alice/WorkDiary/', [
                ['path' => '/remote.php/dav/files/alice/WorkDiary/re-1.pdf', 'fileid' => '101', 'etag' => 'e1', 'mime' => 'application/pdf', 'size' => 1234],
                ['path' => '/remote.php/dav/files/alice/WorkDiary/Sub/', 'dir' => true, 'fileid' => '102', 'etag' => 'edir'],
            ]),
        ]);

        $children = $client->listChildren('WorkDiary');

        $this->assertCount(2, $children);
        $this->assertSame('WorkDiary/re-1.pdf', $children[0]['path']);
        $this->assertFalse($children[0]['is_dir']);
        $this->assertSame('101', $children[0]['fileid']);
        $this->assertSame('e1', $children[0]['etag']);
        $this->assertSame('application/pdf', $children[0]['mime']);
        $this->assertSame(1234, $children[0]['size']);
        $this->assertTrue($children[1]['is_dir']);
        $this->assertSame('WorkDiary/Sub', $children[1]['path']);
    }

    public function test_list_children_throws_not_found_on_404(): void {
        $client = $this->client([new Response(404)]);

        $this->expectException(NextcloudNotFoundException::class);
        $client->listChildren('Gone');
    }

    public function test_quota_parses_used_and_total(): void {
        $xml = '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"><d:response>'
            . '<d:href>/remote.php/dav/files/alice/</d:href><d:propstat><d:prop>'
            . '<d:quota-available-bytes>600</d:quota-available-bytes>'
            . '<d:quota-used-bytes>400</d:quota-used-bytes>'
            . '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response></d:multistatus>';
        $client = $this->client([new Response(207, [], $xml)]);

        $this->assertSame(['total' => 1000, 'used' => 400], $client->quota());
    }

    public function test_resumable_upload_issues_chunks_and_verifies_size(): void {
        $path = tempnam(sys_get_temp_dir(), 'ncup');
        file_put_contents($path, 'ABCDEFGHIJ'); // 10 Bytes, chunkSize 4 ⇒ 3 Chunks

        $client = $this->client([
            new Response(201), // MKCOL Zielordner-Segment „backup"
            new Response(201), // MKCOL Zielordner-Segment „backup/gen"
            new Response(201), // MKCOL Upload-Session
            new Response(201), // PUT Chunk 0
            new Response(201), // PUT Chunk 1
            new Response(201), // PUT Chunk 2
            new Response(201), // MOVE .file → Ziel
            new Response(200, ['Content-Length' => '10']), // HEAD Verifikation
        ]);

        $size = $client->uploadResumable($path, 'backup/gen/part-1.enc');
        @unlink($path);

        $this->assertSame(10, $size);
        $methods = array_map(static fn ($e) => $e['request']->getMethod(), $this->history);
        $this->assertSame(['MKCOL', 'MKCOL', 'MKCOL', 'PUT', 'PUT', 'PUT', 'MOVE', 'HEAD'], $methods);
    }

    public function test_resumable_upload_rejects_size_mismatch(): void {
        $path = tempnam(sys_get_temp_dir(), 'ncup');
        file_put_contents($path, 'ABCDEFGHIJ');

        $client = $this->client([
            new Response(201), new Response(201), new Response(201),
            new Response(201), new Response(201), new Response(201),
            new Response(201),
            new Response(200, ['Content-Length' => '7']), // falsche Remote-Größe
        ]);

        $this->expectException(RuntimeException::class);
        try {
            $client->uploadResumable($path, 'backup/gen/part-1.enc');
        } finally {
            @unlink($path);
        }
    }

    public function test_delete_is_idempotent_on_404(): void {
        $client = $this->client([new Response(404)]);

        $this->assertTrue($client->deletePath('backup/gen'));
    }
}
