<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginPackageLayoutRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Architektur-Gate (Konsolidierungs-Audit 2026-10, k2-09): ein Plugin-Paket
 * hat einen festen Aufbau. In der Wurzel liegen nur Plugin, ServiceProvider,
 * Config-Klasse, `config.php` und `routes.php`; API-Clients liegen in `Api`,
 * Exceptions in `Exceptions`, Dienste in `Services`. Vorher standen 59
 * Dateien in 14 Plugin-Wurzeln, Clients an vier Orten und Exceptions an drei.
 */
class PluginPackageLayoutRuleTest extends TestCase {
    /** Plattform-Ordner ohne Plugin-Paket. */
    private const NOT_A_PLUGIN = ['Support', 'Contracts'];

    public function test_plugin_root_holds_only_plugin_provider_and_config(): void {
        $violations = [];
        foreach ($this->pluginDirs() as $plugin => $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $name = basename($file);
                $allowed = in_array($name, [$plugin . 'Plugin.php', $plugin . 'ServiceProvider.php', 'config.php', 'routes.php'], true)
                    || str_ends_with($name, 'Config.php');
                if (! $allowed) {
                    $violations[] = "app/Plugins/$plugin/$name";
                }
            }
        }

        $this->assertSame([], $violations, "In die Plugin-Wurzel gehören nur Plugin, ServiceProvider, Config, config.php und routes.php — Dienste nach Services/, Clients nach Api/, Exceptions nach Exceptions/:\n" . implode("\n", $violations));
    }

    public function test_api_clients_live_in_api_and_exceptions_in_exceptions(): void {
        $violations = [];
        foreach ($this->pluginDirs() as $plugin => $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $entry) {
                $path = $entry->getPathname();
                $relative = substr($path, strlen($dir) + 1);
                if (! str_ends_with($path, '.php') || str_starts_with($relative, 'Resources/') || str_starts_with($relative, 'Database/')) {
                    continue;
                }
                $name = basename($path);
                if (preg_match('/Client(Factory)?\.php$/', $name) === 1 && ! str_starts_with($relative, 'Api/')) {
                    $violations[] = "app/Plugins/$plugin/$relative — API-Client gehört nach Api/";
                }
                if (str_ends_with($name, 'Exception.php') && ! str_starts_with($relative, 'Exceptions/')) {
                    $violations[] = "app/Plugins/$plugin/$relative — Exception gehört nach Exceptions/";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /** @return array<string, string> Plugin-Name => Verzeichnis */
    private function pluginDirs(): array {
        $base = dirname(__DIR__, 3) . '/app/Plugins';
        $dirs = [];
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry[0] !== '.' && is_dir($base . '/' . $entry) && ! in_array($entry, self::NOT_A_PLUGIN, true)) {
                $dirs[$entry] = $base . '/' . $entry;
            }
        }

        return $dirs;
    }
}
