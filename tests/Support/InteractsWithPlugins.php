<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InteractsWithPlugins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Models\{Organization, PluginSetting};
use App\Plugins\Contracts\Plugin;
use App\Plugins\PluginManager;
use App\Support\OrganizationContext;

/**
 * Test-Helfer für Plugin-Tests (Review 2026-08, W5d/F7): ersetzt den
 * handgebauten Dreiklang aus Manager-Bindung, PluginSetting-Zeile und
 * Org-Kontext, den vorher jeder Plugin-Test einzeln aufbaute.
 */
trait InteractsWithPlugins {
    /**
     * Bindet einen frischen PluginManager mit genau den übergebenen Plugins
     * an den Container (ersetzt die Auto-Discovery für den Test).
     */
    protected function registerPlugins(Plugin ...$plugins): PluginManager {
        $manager = new PluginManager;
        foreach ($plugins as $plugin) {
            $manager->register($plugin);
        }
        $this->app->instance(PluginManager::class, $manager);

        return $manager;
    }

    /**
     * Aktiviert ein Plugin für eine Organisation (plugin_settings-Zeile).
     *
     * @param  array<string, mixed>  $settings
     */
    protected function enablePluginFor(Organization $organization, string $pluginId, array $settings = []): PluginSetting {
        return PluginSetting::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => $pluginId,
            'enabled' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Führt $fn mit gebundenem Org-Kontext aus (Restore im finally) — wie der
     * geplante Healthcheck-Lauf bzw. die Request-Middleware.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $fn
     * @return TReturn
     */
    protected function withPluginOrgContext(Organization $organization, callable $fn): mixed {
        return OrganizationContext::run($organization, $fn);
    }
}
