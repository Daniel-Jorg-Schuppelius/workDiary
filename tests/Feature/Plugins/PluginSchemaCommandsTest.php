<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSchemaCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\PluginState;
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7: plugin:install / plugin:upgrade / plugin:uninstall.
 * Der Probe-Plugin liefert ein LEERES Migrations-Verzeichnis — der
 * Zustandswechsel in plugin_states ist damit auch auf MariaDB testbar (kein
 * DDL, kein implizites Commit; der echte Migrations-Lifecycle steht in
 * PluginSchemaLifecycleTest, SQLite-only).
 */
class PluginSchemaCommandsTest extends TestCase {
    use RefreshDatabase;

    private CommandProbePlugin $plugin;

    private string $migrationsPath;

    protected function setUp(): void {
        parent::setUp();

        $this->migrationsPath = storage_path('framework/testing/plugin-cmd-migrations-' . bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->migrationsPath);

        $this->plugin = new CommandProbePlugin($this->migrationsPath);
        $manager = new PluginManager;
        $manager->register($this->plugin);
        $this->app->instance(PluginManager::class, $manager);
    }

    protected function tearDown(): void {
        File::deleteDirectory($this->migrationsPath);
        parent::tearDown();
    }

    public function test_install_records_the_plugin_state(): void {
        $this->artisan('plugin:install cmd-probe')
            ->expectsOutputToContain('cmd-probe installiert (v1.0.0)')
            ->assertExitCode(0);

        $state = PluginState::query()->where('plugin_id', 'cmd-probe')->firstOrFail();
        $this->assertSame('1.0.0', $state->installed_version);
        $this->assertNotNull($state->installed_at);
        $this->assertSame(1, $this->plugin->installCalls);
    }

    public function test_install_fails_for_an_unknown_plugin(): void {
        $this->artisan('plugin:install gibt-es-nicht')
            ->expectsOutputToContain('Plugin nicht gefunden: gibt-es-nicht')
            ->assertExitCode(1);
    }

    public function test_upgrade_bumps_the_installed_version(): void {
        $this->artisan('plugin:install cmd-probe')->assertExitCode(0);

        $this->plugin->schemaVersion = '1.1.0';
        $this->artisan('plugin:upgrade cmd-probe')
            ->expectsOutputToContain('cmd-probe aktualisiert auf v1.1.0')
            ->assertExitCode(0);

        $state = PluginState::query()->where('plugin_id', 'cmd-probe')->firstOrFail();
        $this->assertSame('1.1.0', $state->installed_version);
        // Kein erneuter Fresh-Install: onInstall() lief nur beim ersten Mal.
        $this->assertSame(1, $this->plugin->installCalls);
    }

    public function test_upgrade_is_a_noop_when_already_current(): void {
        $this->artisan('plugin:install cmd-probe')->assertExitCode(0);

        $this->artisan('plugin:upgrade cmd-probe')
            ->expectsOutputToContain('cmd-probe: bereits aktuell (v1.0.0)')
            ->assertExitCode(0);
    }

    public function test_upgrade_fails_for_an_unknown_plugin(): void {
        $this->artisan('plugin:upgrade nope')
            ->expectsOutputToContain('Plugin nicht gefunden: nope')
            ->assertExitCode(1);
    }

    public function test_uninstall_clears_the_plugin_state(): void {
        $this->artisan('plugin:install cmd-probe')->assertExitCode(0);

        $this->artisan('plugin:uninstall cmd-probe --force')
            ->expectsOutputToContain('cmd-probe deinstalliert.')
            ->assertExitCode(0);

        $state = PluginState::query()->where('plugin_id', 'cmd-probe')->firstOrFail();
        $this->assertNull($state->installed_version);
        $this->assertNull($state->installed_at, 'Re-Installation muss wieder ein Fresh-Install sein (A14).');
        $this->assertSame(1, $this->plugin->uninstallCalls);
    }

    public function test_uninstall_fails_for_an_unknown_plugin(): void {
        $this->artisan('plugin:uninstall nope --force')
            ->expectsOutputToContain('Plugin nicht gefunden: nope')
            ->assertExitCode(1);
    }
}

final class CommandProbePlugin implements Plugin {
    use PluginDefaults;

    public string $schemaVersion = '1.0.0';

    public int $installCalls = 0;

    public int $uninstallCalls = 0;

    public function __construct(private readonly string $migrationsPath) {}

    public function id(): string {
        return 'cmd-probe';
    }
    public function name(): string {
        return 'Command Probe';
    }
    public function version(): string {
        return '1.0.0';
    }
    public function description(): string {
        return '';
    }
    public function isEnabled(): bool {
        return true;
    }
    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }
    public function adminPanel(): ?array {
        return null;
    }
    public function serviceProvider(): ?string {
        return null;
    }
    public function settingsSchema(): array {
        return [];
    }
    public function migrationsPath(): ?string {
        return $this->migrationsPath;
    }
    public function schemaVersion(): string {
        return $this->schemaVersion;
    }
    public function onInstall(): void {
        $this->installCalls++;
    }
    public function onUninstall(): void {
        $this->uninstallCalls++;
    }
}
