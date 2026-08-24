<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\License;

use App\Services\Licensing\{LicenseSeal, LicenseService, LicenseStatus};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: Lizenz-Kette keygen → issue → install → show sowie
 * seal/unseal. Schlüsselpaar wird pro Test frisch erzeugt; key_path/seal_path
 * zeigen auf eindeutige Testdateien, damit keine echte Installation berührt
 * wird (Aufräumen im tearDown).
 */
class LicenseCommandsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private string $publicB64;
    private string $privateB64;

    /** @var list<string> im tearDown zu löschende Dateien */
    private array $cleanupFiles = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $keypair = sodium_crypto_sign_keypair();
        $this->publicB64 = LicenseService::b64Encode(sodium_crypto_sign_publickey($keypair));
        $this->privateB64 = LicenseService::b64Encode(sodium_crypto_sign_secretkey($keypair));

        $uid = bin2hex(random_bytes(6));
        config([
            'license.public_key' => $this->publicB64,
            'license.private_key' => '',
            'license.key' => null,
            'license.key_path' => "testing/license-{$uid}.key",
            'license.seal_path' => "testing/license-seal-{$uid}.php",
            'license.cache_ttl' => 0,
        ]);

        $this->cleanupFiles = [
            storage_path('app/' . config('license.key_path')),
            LicenseSeal::path(),
        ];
        LicenseSeal::flushCache();
    }

    protected function tearDown(): void {
        foreach ($this->cleanupFiles as $file) {
            File::delete($file);
        }
        LicenseSeal::flushCache();
        parent::tearDown();
    }

    private function tmpFile(string $suffix): string {
        $path = storage_path('app/testing/license-cmd-' . bin2hex(random_bytes(6)) . $suffix);
        File::ensureDirectoryExists(dirname($path));
        $this->cleanupFiles[] = $path;

        return $path;
    }

    /** Stellt einen gültigen Schlüssel über `license:issue --out` aus. */
    private function issueKey(array $options = []): string {
        $out = $this->tmpFile('.lic');
        $this->artisan('license:issue', array_merge([
            '--licensee' => 'Acme GmbH',
            '--private-key' => $this->privateB64,
            '--out' => $out,
        ], $options))->assertExitCode(0);

        return trim((string) File::get($out));
    }

    // ── license:keygen ───────────────────────────────────────────────────────

    public function test_keygen_prints_and_writes_a_keypair(): void {
        $out = $this->tmpFile('.env');

        $this->artisan('license:keygen', ['--out' => $out])
            ->expectsOutputToContain('LICENSE_PUBLIC_KEY=')
            ->assertExitCode(0);

        $contents = (string) File::get($out);
        $this->assertStringContainsString('LICENSE_PUBLIC_KEY=', $contents);
        $this->assertStringContainsString('LICENSE_PRIVATE_KEY=', $contents);
    }

    // ── license:issue ────────────────────────────────────────────────────────

    public function test_issue_produces_a_verifiable_key(): void {
        $key = $this->issueKey();

        $result = app(LicenseService::class)->verify($key);
        $this->assertSame(LicenseStatus::Valid, $result->status);
        $this->assertSame('Acme GmbH', $result->payload?->licensee);
    }

    public function test_issue_requires_a_licensee(): void {
        $this->artisan('license:issue', ['--private-key' => $this->privateB64])
            ->expectsOutputToContain('--licensee ist erforderlich.')
            ->assertExitCode(1);
    }

    public function test_issue_rejects_a_malformed_private_key(): void {
        $this->artisan('license:issue', [
            '--licensee' => 'Acme GmbH',
            '--private-key' => 'kein-key',
        ])->expectsOutputToContain('Private Key hat falsches Format.')->assertExitCode(1);
    }

    // ── license:install / license:show ───────────────────────────────────────

    public function test_full_chain_keygen_issue_install_show(): void {
        // Kette mit frisch generierten Keys aus license:keygen (nicht setUp).
        $keys = $this->tmpFile('.env');
        $this->artisan('license:keygen', ['--out' => $keys])->assertExitCode(0);

        $contents = (string) File::get($keys);
        preg_match('/^LICENSE_PUBLIC_KEY=(\S+)$/m', $contents, $pub);
        preg_match('/^LICENSE_PRIVATE_KEY=(\S+)$/m', $contents, $priv);
        config(['license.public_key' => $pub[1]]);

        $lic = $this->tmpFile('.lic');
        $this->artisan('license:issue', [
            '--licensee' => 'Ketten AG',
            '--plan' => 'pro',
            '--private-key' => $priv[1],
            '--out' => $lic,
        ])->assertExitCode(0);

        // install akzeptiert einen Dateipfad als Argument.
        $this->artisan('license:install', ['key' => $lic])
            ->expectsOutputToContain('Status: valid')
            ->assertExitCode(0);
        $this->assertFileExists(storage_path('app/' . config('license.key_path')));

        $this->artisan('license:show')
            ->expectsOutputToContain('Ketten AG')
            ->assertExitCode(0);
    }

    public function test_install_rejects_garbage_keys(): void {
        $this->artisan('license:install', ['key' => 'kaputt'])->assertExitCode(1);
        $this->assertFileDoesNotExist(storage_path('app/' . config('license.key_path')));
    }

    public function test_install_binds_a_license_to_an_organization(): void {
        $this->organization->forceFill(['license_uid' => 'org-bind-1'])->save();
        $key = $this->issueKey(['--org' => 'org-bind-1', '--plan' => 'enterprise']);

        $this->artisan('license:install', ['key' => $key, '--org' => 'org-bind-1'])
            ->assertExitCode(0);

        $this->organization->refresh();
        $this->assertSame($key, $this->organization->license_key);
        $this->assertSame('enterprise', (string) $this->organization->plan);
    }

    public function test_install_fails_for_an_unknown_organization(): void {
        $key = $this->issueKey();

        $this->artisan('license:install', ['key' => $key, '--org' => 'gibt-es-nicht'])
            ->expectsOutputToContain('Organisation nicht gefunden')
            ->assertExitCode(1);
    }

    public function test_show_without_installed_license_fails(): void {
        $this->artisan('license:show')
            ->expectsOutputToContain('Status   : missing')
            ->assertExitCode(1);
    }

    // ── license:seal ─────────────────────────────────────────────────────────

    public function test_seal_writes_and_unseal_removes_the_seal_file(): void {
        $this->artisan('license:seal')
            ->expectsOutputToContain('Seal geschrieben')
            ->assertExitCode(0);

        $this->assertFileExists(LicenseSeal::path());
        LicenseSeal::flushCache();
        $this->assertTrue(LicenseSeal::isSealed());
        $this->assertSame($this->publicB64, LicenseSeal::publicKey());

        $this->artisan('license:seal', ['--unseal' => true])->assertExitCode(0);
        $this->assertFileDoesNotExist(LicenseSeal::path());
        $this->assertFalse(LicenseSeal::isSealed());
    }

    public function test_seal_requires_a_public_key(): void {
        config(['license.public_key' => '']);

        $this->artisan('license:seal')
            ->expectsOutputToContain('Kein Public Key übergeben.')
            ->assertExitCode(1);
    }
}
