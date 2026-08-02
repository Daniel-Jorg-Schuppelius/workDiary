<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MakePluginCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Plugin;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Gerüst für ein neues Plugin (Review 2026-08, W5d): erzeugt Plugin-Klasse
 * (auf {@see \App\Plugins\AbstractPlugin}), ServiceProvider, die
 * verpflichtende config.php und ein Test-Skeleton. Konventionen siehe
 * WorkDiary-Architecture/plugin-autorenleitfaden.md.
 */
class MakePluginCommand extends Command {
    protected $signature = 'make:plugin {name : Studly-Name, z. B. "Acme" → app/Plugins/Acme/AcmePlugin.php}';

    protected $description = 'Erzeugt das Grundgerüst eines neuen Plugins (Klasse, Provider, config.php, Test).';

    public function handle(): int {
        $studly = Str::studly((string) $this->argument('name'));
        // ID-Konvention für neue Plugins: Bindestrich (kebab-case).
        $id = Str::kebab($studly);
        $dir = app_path('Plugins/' . $studly);

        if (is_dir($dir)) {
            $this->error("Verzeichnis existiert bereits: {$dir}");

            return self::FAILURE;
        }

        mkdir($dir, 0755, true);
        file_put_contents($dir . '/' . $studly . 'Plugin.php', $this->pluginStub($studly, $id));
        file_put_contents($dir . '/' . $studly . 'ServiceProvider.php', $this->providerStub($studly, $id));
        file_put_contents($dir . '/config.php', $this->configStub($id));

        $testDir = base_path('tests/Feature/Plugins/' . $studly);
        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        file_put_contents($testDir . '/' . $studly . 'PluginTest.php', $this->testStub($studly, $id));

        $this->info("Plugin-Gerüst erzeugt: app/Plugins/{$studly}/ (ID: {$id})");
        $this->line('Nächste Schritte: capabilities() + settingsSchema() ausfüllen, healthCheck() implementieren,');
        $this->line('dann `php artisan plugin:doctor` — Leitfaden: WorkDiary-Architecture/plugin-autorenleitfaden.md');

        return self::SUCCESS;
    }

    private function pluginStub(string $studly, string $id): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Plugins\\{$studly};

use App\\Plugins\\AbstractPlugin;
use App\\Plugins\\Contracts\\SettingsField;
use App\\Plugins\\PluginHealth;

class {$studly}Plugin extends AbstractPlugin {
    public const ID = '{$id}';

    public const SERVICE_PROVIDER = {$studly}ServiceProvider::class;

    public function name(): string {
        return '{$studly}';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return '';
    }

    public function capabilities(): array {
        // Fähigkeiten ankündigen — jedes Capability verlangt sein Contract-Interface
        // (plugin:doctor prüft das). Kern-Fähigkeiten: App\\Plugins\\Contracts\\PluginCapability.
        return [];
    }

    public function settingsSchema(): array {
        return [
            SettingsField::password('api_key', 'API-Key', required: true)->toArray(),
        ];
    }

    public function healthCheck(): PluginHealth {
        // Einfacher Ping-Check — für mehrstufige Prüfungen (Connection-Status,
        // differenzierte Fehlercodes) die Stufen direkt ausformulieren.
        return PluginHealth::pingHealth(
            ping: fn(): bool => true, // TODO: echten Verbindungscheck einsetzen
            unreachableMessage: __('{$studly} nicht erreichbar.'),
            configured: \$this->isEnabled(),
            notConfiguredMessage: __('{$studly} ist nicht konfiguriert.'),
            notConfiguredStatus: PluginHealth::STATUS_DEGRADED,
        );
    }
}
PHP;
    }

    private function providerStub(string $studly, string $id): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Plugins\\{$studly};

use App\\Plugins\\Support\\PluginServiceProviderBase;

/**
 * Konventionspfade (config.php Pflicht, routes.php/Resources/views optional)
 * lädt die Basisklasse; Individuelles in registerPlugin()/bootPlugin().
 */
class {$studly}ServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return {$studly}Plugin::ID;
    }
}
PHP;
    }

    private function configStub(string $id): string {
        return <<<PHP
<?php

// ENV-Fallbacks für Tests/Konsolen-Kontexte — produktiv kommt die
// Konfiguration pro Organisation aus plugin_settings (verschlüsselt).
return [
    'enabled' => (bool) env('PLUGINS_' . strtoupper(str_replace('-', '_', '{$id}')) . '_ENABLED', false),
];
PHP;
    }

    private function testStub(string $studly, string $id): string {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Tests\\Feature\\Plugins\\{$studly};

use App\\Plugins\\{$studly}\\{$studly}Plugin;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\Support\\InteractsWithPlugins;
use Tests\\TestCase;

class {$studly}PluginTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;

    public function test_contract_basics(): void {
        \$plugin = app({$studly}Plugin::class);

        \$this->assertSame('{$id}', \$plugin->id());
        \$this->assertNotSame('', \$plugin->name());
    }
}
PHP;
    }
}
