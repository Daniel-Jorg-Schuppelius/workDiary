<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstallationManagerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Install;

use App\Enums\User\UserRole;
use App\Models\Organization;
use App\Services\Install\{EnvWriter, InstallationManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallationManagerTest extends TestCase {
    use RefreshDatabase;

    private string $envPath;

    private string $lockPath;

    protected function setUp(): void {
        parent::setUp();

        $dir = sys_get_temp_dir() . '/wd-install-' . uniqid();
        @mkdir($dir, 0775, true);
        $this->envPath = $dir . '/.env';
        $this->lockPath = $dir . '/installed';
        file_put_contents($this->envPath, "APP_NAME=WorkDiary\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\n");
    }

    protected function tearDown(): void {
        @unlink($this->envPath);
        @unlink($this->lockPath);
        parent::tearDown();
    }

    private function manager(): InstallationManager {
        return new InstallationManager(new EnvWriter($this->envPath), $this->lockPath);
    }

    public function test_ensure_app_key_generates_when_empty(): void {
        $manager = $this->manager();

        $this->assertFalse($manager->hasAppKey());
        $this->assertTrue($manager->ensureAppKey());
        $this->assertTrue($manager->hasAppKey());

        $key = (new EnvWriter($this->envPath))->get('APP_KEY');
        $this->assertNotNull($key);
        $this->assertStringStartsWith('base64:', (string) $key);
    }

    public function test_ensure_app_key_never_overwrites_existing(): void {
        $writer = new EnvWriter($this->envPath);
        $writer->set('APP_KEY', 'base64:AAAABBBBCCCCDDDDEEEEFFFFGGGGHHHHIIIIJJJJKKK=');
        $manager = $this->manager();

        $this->assertFalse($manager->ensureAppKey());
        $this->assertSame(
            'base64:AAAABBBBCCCCDDDDEEEEFFFFGGGGHHHHIIIIJJJJKKK=',
            (new EnvWriter($this->envPath))->get('APP_KEY'),
        );
    }

    public function test_mark_installed_writes_lock_file(): void {
        $manager = $this->manager();

        $this->assertFalse(is_file($this->lockPath));
        $manager->markInstalled();
        $this->assertTrue(is_file($this->lockPath));
    }

    public function test_is_installed_honours_config_override(): void {
        $manager = $this->manager();

        config(['app.installed' => true]);
        $this->assertTrue($manager->isInstalled());

        config(['app.installed' => false]);
        $this->assertFalse($manager->isInstalled());
    }

    public function test_requirements_flag_missing_extension(): void {
        $checks = $this->manager()->requirements('sqlite');
        $this->assertNotEmpty($checks);
        foreach ($checks as $check) {
            $this->assertArrayHasKey('ok', $check);
            $this->assertArrayHasKey('label', $check);
        }
    }

    public function test_configure_app_persists_values(): void {
        $this->manager()->configureApp([
            'app_name' => 'Acme',
            'app_url' => 'https://acme.test',
            'app_env' => 'production',
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
        ]);

        $writer = new EnvWriter($this->envPath);
        $this->assertSame('Acme', $writer->get('APP_NAME'));
        $this->assertSame('https://acme.test', $writer->get('APP_URL'));
        $this->assertSame('de', $writer->get('APP_LOCALE'));
    }

    public function test_configure_mail_and_integrations_persist(): void {
        $manager = $this->manager();
        $manager->configureMail([
            'mailer' => 'smtp',
            'host' => 'mail.acme.test',
            'port' => 587,
            'from_address' => 'no-reply@acme.test',
            'from_name' => 'Acme',
        ]);
        $manager->configureIntegrations([
            'lexoffice_api_key' => 'lxo-123',
        ]);

        $writer = new EnvWriter($this->envPath);
        $this->assertSame('smtp', $writer->get('MAIL_MAILER'));
        $this->assertSame('mail.acme.test', $writer->get('MAIL_HOST'));
        $this->assertSame('lxo-123', $writer->get('LEXOFFICE_API_KEY'));
    }

    public function test_test_connection_sqlite_creates_file(): void {
        $db = sys_get_temp_dir() . '/wd-test-' . uniqid() . '.sqlite';
        $this->assertTrue($this->manager()->testConnection(['driver' => 'sqlite', 'database' => $db]));
        $this->assertTrue(is_file($db));
        @unlink($db);
    }

    public function test_create_organization_and_admin(): void {
        $user = $this->manager()->createOrganizationAndAdmin([
            'org_name' => 'Acme GmbH',
            'name' => 'Admin',
            'email' => 'admin@acme.test',
            'password' => 'Super-Secret-123',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'admin@acme.test']);
        $this->assertDatabaseHas('organizations', ['name' => 'Acme GmbH']);

        $org = Organization::where('name', 'Acme GmbH')->first();
        $this->assertNotNull($org);
        $this->assertSame($user->id, $org->owner_id);

        setPermissionsTeamId($org->id);
        $user->load('roles');
        $this->assertTrue($user->hasRole(UserRole::Admin->value));
    }
}
