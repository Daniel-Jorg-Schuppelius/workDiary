<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSchemaLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\PluginState;
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginSchemaManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reparierter Schema-Lifecycle (Review 2026-08, W6 / Entscheidung E-7):
 * Install → Uninstall → Re-Install-Zyklus, transaktionaler onInstall-Hook,
 * Downgrade-Schutz.
 */
class PluginSchemaLifecycleTest extends TestCase {
    use RefreshDatabase;

    private function manager(): PluginSchemaManager {
        return app(PluginSchemaManager::class);
    }

    public function test_install_uninstall_reinstall_cycle(): void {
        $plugin = new SchemaProbePlugin;

        $this->assertTrue($this->manager()->install($plugin));
        $this->assertTrue(Schema::hasTable('wd_schema_probe'));
        $this->assertSame(1, $plugin->installCalls, 'onInstall() läuft beim Fresh-Install.');

        $state = PluginState::query()->where('plugin_id', 'schema-probe')->firstOrFail();
        $this->assertSame('1.0.0', $state->installed_version);
        $this->assertNotNull($state->installed_at);

        $this->assertTrue($this->manager()->uninstall($plugin));
        $this->assertFalse(Schema::hasTable('wd_schema_probe'), 'Uninstall rollt die Plugin-Migrationen zurück.');
        $this->assertSame(1, $plugin->uninstallCalls);

        $state->refresh();
        $this->assertNull($state->installed_version);
        $this->assertNull($state->installed_at, 'installed_at muss geleert werden — sonst ist die Re-Installation kein Fresh-Install (A14).');

        $this->assertTrue($this->manager()->install($plugin));
        $this->assertTrue(Schema::hasTable('wd_schema_probe'));
        $this->assertSame(2, $plugin->installCalls, 'Re-Installation läuft wieder als Fresh-Install.');
    }

    public function test_throwing_on_install_leaves_plugin_uninstalled(): void {
        $plugin = new SchemaProbePlugin;
        $plugin->failOnInstall = true;

        try {
            $this->manager()->install($plugin);
            $this->fail('onInstall()-Ausnahme muss propagieren.');
        } catch (\RuntimeException) {
            // erwartet
        }

        $state = PluginState::query()->where('plugin_id', 'schema-probe')->first();
        $this->assertTrue($state === null || $state->installed_at === null, 'Wirft onInstall(), gilt das Plugin nicht als installiert (A14).');
    }

    public function test_downgrade_is_refused(): void {
        $plugin = new SchemaProbePlugin;
        $this->manager()->install($plugin);
        PluginState::query()->where('plugin_id', 'schema-probe')->update(['installed_version' => '9.9.9']);

        $this->assertFalse($this->manager()->needsUpgrade($plugin), 'Neueres Schema als der Code ist kein Upgrade.');

        $this->expectException(\RuntimeException::class);
        $this->manager()->upgrade($plugin);
    }
}

final class SchemaProbePlugin implements Plugin {
    use PluginDefaults;

    public int $installCalls = 0;

    public int $uninstallCalls = 0;

    public bool $failOnInstall = false;

    public function id(): string {
        return 'schema-probe';
    }
    public function name(): string {
        return 'Schema Probe';
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
        return base_path('tests/Fixtures/plugin-migrations');
    }
    public function schemaVersion(): string {
        return '1.0.0';
    }
    public function onInstall(): void {
        if ($this->failOnInstall) {
            throw new \RuntimeException('install-hook boom');
        }
        $this->installCalls++;
    }
    public function onUninstall(): void {
        $this->uninstallCalls++;
    }
}
