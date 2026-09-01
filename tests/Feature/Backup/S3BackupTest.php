<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : S3BackupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Backup;

use App\Enums\Backup\BackupProvider;
use App\Models\Backup\BackupTargetConnection;
use App\Models\User;
use App\Plugins\S3\Api\S3BackupClient;
use Aws\Command;
use Aws\{MockHandler, Result};
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * S3-kompatibles Backupziel (Feature 123, MVP-726): Upload mit
 * Größen-Verifikation, Listing, Löschen — und der Selbsttest, der ein Ziel
 * erst nach Schreiben, Lesen und Löschen als brauchbar gelten lässt.
 *
 * Transport über den SDK-eigenen MockHandler; es geht kein Byte ins Netz.
 */
class S3BackupTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private BackupTargetConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->connection = BackupTargetConnection::factory()->create([
            'provider' => BackupProvider::S3,
            'name' => 'MinIO',
            'server_url' => 'https://s3.example.com',
            'username' => 'AKIAEXAMPLE',
            'access_token' => 'secret',
            'root_folder_ref' => 'workdiary/abc123',
            'options' => ['bucket' => 'backup', 'region' => 'eu-central-1', 'path_style' => true],
        ]);
    }

    /** @param list<Result|S3Exception> $results */
    private function client(array $results): S3BackupClient {
        $mock = new MockHandler();
        foreach ($results as $result) {
            $mock->append($result);
        }

        return new S3BackupClient($this->connection, new S3Client([
            'version' => '2006-03-01',
            'region' => 'eu-central-1',
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'handler' => $mock,
        ]));
    }

    private function tempFile(string $content): string {
        $path = tempnam(sys_get_temp_dir(), 's3b');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_upload_verifies_the_remote_size(): void {
        $path = $this->tempFile(str_repeat('x', 128));

        $key = $this->client([
            new Result([]),                       // putObject
            new Result(['ContentLength' => 128]), // headObject
        ])->upload($path, 'part-001.bin');

        $this->assertSame('workdiary/abc123/part-001.bin', $key);
        @unlink($path);
    }

    /**
     * Ein Ziel, das stillschweigend kürzt, fällt sonst erst beim
     * Wiederherstellen auf — dann ist es zu spät.
     */
    public function test_upload_fails_when_the_remote_size_differs(): void {
        $path = $this->tempFile(str_repeat('x', 128));
        $client = $this->client([
            new Result([]),
            new Result(['ContentLength' => 64]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Remote-Größe weicht ab/');

        try {
            $client->upload($path, 'part-001.bin');
        } finally {
            @unlink($path);
        }
    }

    public function test_listing_skips_pseudo_folders(): void {
        $objects = $this->client([
            new Result([
                'Contents' => [
                    ['Key' => 'workdiary/abc123/', 'Size' => 0],
                    ['Key' => 'workdiary/abc123/part-001.bin', 'Size' => 512],
                ],
                'IsTruncated' => false,
            ]),
        ])->listObjects('workdiary/abc123');

        $this->assertCount(1, $objects);
        $this->assertSame('part-001.bin', $objects[0]->name);
        $this->assertSame(512, $objects[0]->size);
    }

    /** Mehr als eine Seite: das Fortsetzungs-Token muss durchlaufen. */
    public function test_listing_follows_continuation_tokens(): void {
        $objects = $this->client([
            new Result([
                'Contents' => [['Key' => 'p/a.bin', 'Size' => 1]],
                'IsTruncated' => true,
                'NextContinuationToken' => 'weiter',
            ]),
            new Result([
                'Contents' => [['Key' => 'p/b.bin', 'Size' => 2]],
                'IsTruncated' => false,
            ]),
        ])->listObjects('p');

        $this->assertCount(2, $objects);
    }

    public function test_self_test_writes_reads_and_deletes(): void {
        $client = $this->client([
            new Result([]),                                              // putObject
            new Result(['Body' => Utils::streamFor('workdiary-selftest')]), // getObject
            new Result([]),                                              // deleteObject
        ]);

        $client->selfTest('workdiary/abc123');

        $this->addToAssertionCount(1);
    }

    public function test_self_test_rejects_a_target_that_returns_other_content(): void {
        $client = $this->client([
            new Result([]),
            new Result(['Body' => Utils::streamFor('etwas anderes')]),
        ]);

        $this->expectException(RuntimeException::class);
        $client->selfTest('workdiary/abc123');
    }

    /** Die SDK-Meldung trägt Endpoint und Signaturkopf — sie darf nicht durchschlagen. */
    public function test_sdk_errors_are_wrapped_without_leaking_the_request(): void {
        $client = $this->client([
            new S3Exception(
                'Signature mismatch; endpoint https://s3.example.com; key AKIAEXAMPLE',
                new Command('DeleteObject'),
                ['code' => 'SignatureDoesNotMatch'],
            ),
        ]);

        try {
            $client->delete('workdiary/abc123/part-001.bin');
            $this->fail('Erwartet: RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('AKIAEXAMPLE', $e->getMessage());
            $this->assertStringContainsString('SignatureDoesNotMatch', $e->getMessage());
        }
    }

    /** S3 kennt kein Kontingent — geraten wird nichts. */
    public function test_quota_is_unknown(): void {
        $this->assertSame(['total' => null, 'used' => null], $this->client([])->quota());
    }

    public function test_account_names_bucket_and_endpoint(): void {
        $account = $this->client([])->account();

        $this->assertSame('backup', $account->externalId);
        $this->assertStringContainsString('s3.example.com', $account->label);
    }

    /** Das Ziel ist reines Plattform-Admin-Gebiet. */
    public function test_connect_form_requires_platform_admin(): void {
        $this->actingAs($this->orgAdmin())
            ->get(route('admin.backup-targets.s3.connect-form'))
            ->assertForbidden();
    }

    /** Ein Endpoint ohne HTTPS oder ins interne Netz wird abgewiesen. */
    public function test_endpoint_must_be_https_and_public(): void {
        $admin = User::factory()->create(['organization_id' => $this->organization->id, 'is_platform_admin' => true]);
        $base = [
            'name' => 'MinIO',
            'region' => 'eu-central-1',
            'bucket' => 'backup',
            'access_key' => 'AKIA',
            'secret_key' => 'geheim',
        ];

        $this->actingAs($admin)
            ->post(route('admin.backup-targets.s3.connect'), $base + ['endpoint' => 'http://s3.example.com'])
            ->assertSessionHasErrors('endpoint');

        $this->actingAs($admin)
            ->post(route('admin.backup-targets.s3.connect'), $base + ['endpoint' => 'https://192.168.10.5:9000'])
            ->assertSessionHasErrors('endpoint');
    }

    /** Bucket-Namen folgen der S3-Regel — ein Tippfehler soll hier auffallen, nicht im Ernstfall. */
    public function test_bucket_name_is_validated(): void {
        $admin = User::factory()->create(['organization_id' => $this->organization->id, 'is_platform_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.backup-targets.s3.connect'), [
                'name' => 'MinIO',
                'region' => 'eu-central-1',
                'bucket' => 'Backup_Bucket',
                'access_key' => 'AKIA',
                'secret_key' => 'geheim',
            ])
            ->assertSessionHasErrors('bucket');
    }

    public function test_missing_bucket_is_refused(): void {
        $this->connection->forceFill(['options' => ['region' => 'eu-central-1']])->save();

        $this->expectException(RuntimeException::class);
        $this->client([])->listObjects('p');
    }
}
