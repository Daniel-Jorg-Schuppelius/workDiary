<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseHostBindingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Services\Licensing\{LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (license-1): Die Domainbindung wurde am
 * Host-Header bewertet. Ein anonymer Request mit fremdem `Host:` vergiftete
 * damit den installationsweiten Lizenz-Cache (alle Mandanten ohne eigene
 * Lizenz fielen auf `free`), und die S-15-Sperre gegen das Ersetzen der
 * Betreiberlizenz ließ sich auf demselben Weg aushebeln.
 */
final class LicenseHostBindingTest extends TestCase {
    use RefreshDatabase;

    private string $secretKey;

    protected function setUp(): void {
        parent::setUp();
        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
        config()->set('license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));
        config()->set('license.cache_ttl', 0);
        config()->set('app.url', 'https://firma.example');
    }

    /** @param array<string, mixed> $overrides */
    private function key(array $overrides = []): string {
        $payload = array_merge([
            'license_id' => bin2hex(random_bytes(8)),
            'licensee' => 'Testkunde',
            'issued_at' => CarbonImmutable::now()->subDay()->toIso8601String(),
            'expires_at' => CarbonImmutable::now()->addYear()->toIso8601String(),
            'plan' => 'pro',
            'addons' => [],
            'domain' => 'firma.example',
        ], $overrides);

        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return CryptoHelper::base64UrlEncode($json) . '.' . CryptoHelper::base64UrlEncode(sodium_crypto_sign_detached($json, $this->secretKey));
    }

    private function installKey(string $key): void {
        $path = storage_path('app/' . config('license.key_path', 'license.key'));
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $key);
    }

    protected function tearDown(): void {
        $path = storage_path('app/' . config('license.key_path', 'license.key'));
        if (File::exists($path)) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_domain_binding_follows_app_url_not_the_host_header(): void {
        $service = app(LicenseService::class);

        $this->assertSame(LicenseStatus::Valid, $service->verify($this->key())->status);

        config()->set('app.url', 'https://andere.example');
        $this->assertSame(LicenseStatus::DomainMismatch, $service->verify($this->key())->status);
    }

    public function test_foreign_host_header_cannot_poison_the_license_cache(): void {
        $this->installKey($this->key());
        config()->set('license.cache_ttl', 300);

        // Anonymer Request mit fremdem Host — früher landete das Ergebnis
        // DomainMismatch im installationsweiten Cache.
        $this->withServerVariables(['HTTP_HOST' => 'x.invalid'])->get(route('login'));

        $this->assertSame(LicenseStatus::Valid, app(LicenseService::class)->current()->status);
    }

    public function test_install_refuses_an_expired_key(): void {
        $service = app(LicenseService::class);
        $expired = $this->key([
            'issued_at' => CarbonImmutable::now()->subYears(2)->toIso8601String(),
            'expires_at' => CarbonImmutable::now()->subYear()->toIso8601String(),
        ]);

        $result = $service->install($expired);

        $this->assertSame(LicenseStatus::Expired, $result->status);
        $this->assertFalse(File::exists(storage_path('app/' . config('license.key_path', 'license.key'))));
    }

    public function test_replacing_an_installed_license_requires_a_platform_operator(): void {
        $this->installKey($this->key());
        // Domainfremde Installation: das Ergebnis ist nicht nutzbar — genau die
        // Lücke, über die die Sperre früher entfiel.
        config()->set('app.url', 'https://andere.example');

        $this->post(route('license.store'), ['license_key' => $this->key()])->assertForbidden();
    }
}
