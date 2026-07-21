<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReleaseManifestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Release;

use App\Services\Release\{ReleaseManifestService, ReleaseVerifier};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * release:manifest erzeugt valides, signiertes/integritätsgesichertes JSON;
 * release:verify erkennt Manipulationen an Prüfsummen und Signatur
 * (Feature 022, MVP).
 */
class ReleaseManifestTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        // Feature 095: release:manifest erzeugt zuerst die Quelltext-Baseline —
        // für den Test auf einen minimalen Scan-Umfang begrenzen.
        config()->set('integrity.paths', ['config']);
        config()->set('integrity.root_files', ['composer.json']);
        config()->set('integrity.vendor.enabled', false);
    }

    public function test_manifest_contains_versions_and_checksums(): void {
        $document = app(ReleaseManifestService::class)->build();

        $this->assertSame('workdiary.release-manifest/v1', $document['schema']);
        $this->assertSame((string) config('app.version'), $document['application']['version']);
        $this->assertSame(PHP_VERSION, $document['runtime']['php']);
        $this->assertNotEmpty($document['runtime']['laravel']);

        // Prüfsummen der echten Lockfiles des Repos sind enthalten.
        $names = array_column($document['artifacts'], 'name');
        $this->assertContains('composer.lock', $names);
        foreach ($document['artifacts'] as $artifact) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $artifact['sha256']);
        }

        $this->assertArrayHasKey('signature', $document);
    }

    public function test_command_writes_manifest_file(): void {
        Storage::fake('local');

        $this->artisan('release:manifest')
            ->expectsOutputToContain('Release-Manifest geschrieben')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists(ReleaseManifestService::STORAGE_PATH));

        $json = (string) Storage::disk('local')->get(ReleaseManifestService::STORAGE_PATH);
        $document = json_decode($json, true);
        $this->assertIsArray($document);
        $this->assertSame('workdiary.release-manifest/v1', $document['schema']);
    }

    public function test_verify_accepts_unsigned_but_checksum_valid_manifest(): void {
        $document = app(ReleaseManifestService::class)->build();

        $result = app(ReleaseVerifier::class)->verify($document);

        $this->assertTrue($result->valid);
        $this->assertFalse($result->signed);
        $this->assertGreaterThanOrEqual(1, $result->checkedArtifacts);
    }

    public function test_verify_detects_tampered_checksum(): void {
        $document = app(ReleaseManifestService::class)->build();

        // Manipuliere die Prüfsumme eines Artefakts.
        $document['artifacts'][0]['sha256'] = str_repeat('0', 64);

        $result = app(ReleaseVerifier::class)->verify($document);

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->issues);
    }

    public function test_signed_manifest_verifies_and_detects_payload_tampering(): void {
        $keypair = sodium_crypto_sign_keypair();
        config()->set('license.private_key', base64_encode(sodium_crypto_sign_secretkey($keypair)));
        config()->set('license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));

        $document = app(ReleaseManifestService::class)->build();
        $this->assertTrue($document['signature']['signed']);

        $verifier = app(ReleaseVerifier::class);
        $clean = $verifier->verify($document);
        $this->assertTrue($clean->valid);
        $this->assertTrue($clean->signatureValid);

        // Nutzlast verändern, Signatur bleibt → Signatur muss ungültig werden.
        $document['application']['version'] = '99.99.99';
        $tampered = $verifier->verify($document);
        $this->assertFalse($tampered->valid);
        $this->assertFalse($tampered->signatureValid);
    }
}
