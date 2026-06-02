<?php
/*
 * Created on   : Mon Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstallWizardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Install;

use App\Services\Install\{EnvWriter, InstallationManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallWizardTest extends TestCase {
    use RefreshDatabase;

    private string $envPath;

    private string $lockPath;

    protected function setUp(): void {
        parent::setUp();

        $dir = sys_get_temp_dir() . '/wd-wizard-' . uniqid();
        @mkdir($dir, 0775, true);
        $this->envPath = $dir . '/.env';
        $this->lockPath = $dir . '/installed';
        // Vorhandener APP_KEY → PrepareInstaller erzeugt keinen neuen.
        file_put_contents(
            $this->envPath,
            "APP_NAME=WorkDiary\nAPP_ENV=production\nAPP_KEY=base64:AAAABBBBCCCCDDDDEEEEFFFFGGGGHHHHIIIIJJJJKKK=\nAPP_DEBUG=false\n",
        );
    }

    protected function tearDown(): void {
        @unlink($this->envPath);
        @unlink($this->lockPath);
        parent::tearDown();
    }

    private function bootFreshInstall(): void {
        config(['app.installed' => false]);
        $this->app->instance(
            InstallationManager::class,
            new InstallationManager(new EnvWriter($this->envPath), $this->lockPath),
        );
    }

    public function test_uninstalled_app_redirects_to_installer(): void {
        $this->bootFreshInstall();

        $this->get('/login')->assertRedirect(route('install.index'));
    }

    public function test_installer_blocked_when_installed(): void {
        // Default-Suite läuft mit APP_INSTALLED=true.
        $this->get('/install')->assertNotFound();
    }

    public function test_requirements_page_renders(): void {
        $this->bootFreshInstall();

        $this->get('/install')->assertOk()->assertViewIs('install.requirements');
    }

    public function test_application_step_persists_env_and_advances(): void {
        $this->bootFreshInstall();

        $this->post('/install/application', [
            'app_name' => 'Acme',
            'app_url' => 'https://acme.test',
            'app_env' => 'production',
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
        ])->assertRedirect(route('install.database'));

        $writer = new EnvWriter($this->envPath);
        $this->assertSame('Acme', $writer->get('APP_NAME'));
        $this->assertSame('https://acme.test', $writer->get('APP_URL'));
    }

    public function test_admin_step_creates_organization_and_user(): void {
        $this->bootFreshInstall();

        $this->post('/install/admin', [
            'org_name' => 'Acme GmbH',
            'name' => 'Admin',
            'email' => 'admin@acme.test',
            'password' => 'Super-Secret-123',
            'password_confirmation' => 'Super-Secret-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('install.mail'));

        $this->assertDatabaseHas('users', ['email' => 'admin@acme.test']);
        $this->assertDatabaseHas('organizations', ['name' => 'Acme GmbH']);
    }

    public function test_mail_and_integrations_steps_persist_env(): void {
        $this->bootFreshInstall();

        $this->post('/install/mail', [
            'mailer' => 'smtp',
            'host' => 'mail.acme.test',
            'port' => 587,
            'from_address' => 'no-reply@acme.test',
            'from_name' => 'Acme',
        ])->assertRedirect(route('install.integrations'));

        $this->post('/install/integrations', [
            'lexoffice_api_key' => 'lxo-123',
        ])->assertRedirect(route('install.finish'));

        $writer = new EnvWriter($this->envPath);
        $this->assertSame('smtp', $writer->get('MAIL_MAILER'));
        $this->assertSame('lxo-123', $writer->get('LEXOFFICE_API_KEY'));
    }

    public function test_complete_marks_installed_and_redirects_to_login(): void {
        $this->bootFreshInstall();

        $this->assertFalse(is_file($this->lockPath));

        $this->post('/install/finish')->assertRedirect(route('login'));

        $this->assertTrue(is_file($this->lockPath));
    }

    public function test_application_step_validation_errors(): void {
        $this->bootFreshInstall();

        $this->post('/install/application', [
            'app_name' => '',
            'app_url' => 'not-a-url',
            'app_env' => 'invalid',
        ])->assertSessionHasErrors(['app_name', 'app_url', 'app_env']);
    }
}
