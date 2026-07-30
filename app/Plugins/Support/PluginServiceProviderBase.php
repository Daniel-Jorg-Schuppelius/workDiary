<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginServiceProviderBase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use RuntimeException;

/**
 * Gemeinsame Basis aller Plugin-ServiceProvider (W3c). Übernimmt die
 * Layout-Konventionen des Plugin-Systems: `config.php` wird im register()
 * unter `plugins.<id>` gemergt; `routes.php` und `Resources/views` werden im
 * boot() geladen, sofern vorhanden (View-Namespace = Plugin-ID).
 * Individuelles (Bindings, Observer, Commands, Registry-Anmeldungen, …)
 * gehört in die Hooks {@see registerPlugin()} / {@see bootPlugin()}.
 */
abstract class PluginServiceProviderBase extends ServiceProvider {
    /** Verzeichnis der konkreten Provider-Klasse (= Plugin-Verzeichnis), lazy ermittelt. */
    private ?string $pluginDir = null;

    /** Plugin-ID — Config-Schlüssel `plugins.<id>` und View-Namespace (Konvention: `XxxPlugin::ID`). */
    abstract protected function pluginId(): string;

    final public function register(): void {
        $this->mergeConfigFrom($this->pluginDir() . '/config.php', 'plugins.' . $this->pluginId());
        $this->registerPlugin();
    }

    final public function boot(): void {
        $routes = $this->pluginDir() . '/routes.php';
        if (is_file($routes)) {
            $this->loadRoutesFrom($routes);
        }

        $views = $this->pluginDir() . '/Resources/views';
        if (is_dir($views)) {
            $this->loadViewsFrom($views, $this->pluginId());
        }

        $this->bootPlugin();
    }

    /** Hook für individuelle register()-Logik (Container-Bindings, Console-Commands, …). */
    protected function registerPlugin(): void {}

    /** Hook für individuelle boot()-Logik (Observer, Dispatcher-/Registry-Anmeldungen, …). */
    protected function bootPlugin(): void {}

    private function pluginDir(): string {
        if ($this->pluginDir === null) {
            $file = (new ReflectionClass(static::class))->getFileName();
            if ($file === false) {
                throw new RuntimeException('Plugin-Verzeichnis für ' . static::class . ' nicht ermittelbar.');
            }
            $this->pluginDir = dirname($file);
        }

        return $this->pluginDir;
    }
}
