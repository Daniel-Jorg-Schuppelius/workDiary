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

use CommonToolkit\Helper\FileSystem\{File, Folder};
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use RuntimeException;

/**
 * Gemeinsame Basis aller Plugin-ServiceProvider (W3c). Übernimmt die
 * Layout-Konventionen des Plugin-Systems: `config.php` wird im register()
 * unter `plugins.<id>` gemergt; `routes.php`, `Resources/views` und
 * `Resources/lang` werden im boot() geladen, sofern vorhanden (View- und
 * Text-Namespace = Plugin-ID, z. B. `__('lexoffice::lexware.handover.title')`;
 * `<sprache>.json` ergänzt die Kern-JSON-Texte, der Kern gewinnt bei Gleichstand).
 * `Database/Migrations` läuft mit dem normalen `migrate` (Tabellen bestehen
 * unabhängig von der Aktivierung); `Plugin::migrationsPath()` bleibt dem
 * eigenen Schema-Lebenszyklus externer Plugins vorbehalten.
 * Individuelles (Bindings, Observer, Commands, Registry-Anmeldungen, …)
 * gehört in die Hooks {@see registerPlugin()} / {@see bootPlugin()}.
 */
abstract class PluginServiceProviderBase extends ServiceProvider {
    /** Verzeichnis der konkreten Provider-Klasse (= Plugin-Verzeichnis), lazy ermittelt. */
    private ?string $pluginDir = null;

    /** Plugin-ID — Config-Schlüssel `plugins.<id>`, View- und Text-Namespace (Konvention: `XxxPlugin::ID`). */
    abstract protected function pluginId(): string;

    final public function register(): void {
        $this->mergeConfigFrom($this->pluginDir() . '/config.php', 'plugins.' . $this->pluginId());
        $this->registerPlugin();
    }

    final public function boot(): void {
        $routes = $this->pluginDir() . '/routes.php';
        if (File::isFile($routes)) {
            $this->loadRoutesFrom($routes);
        }

        // isDirectory statt exists: kein Log je Request und Plugin ohne Views.
        $views = $this->pluginDir() . '/Resources/views';
        if (Folder::isDirectory($views)) {
            $this->loadViewsFrom($views, $this->pluginId());
        }

        $lang = $this->pluginDir() . '/Resources/lang';
        if (Folder::isDirectory($lang)) {
            $this->loadTranslationsFrom($lang, $this->pluginId());
            $this->loadJsonTranslationsFrom($lang);
        }

        $migrations = $this->pluginDir() . '/Database/Migrations';
        if (Folder::isDirectory($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $this->bootPlugin();
    }

    /**
     * Hook für individuelle register()-Logik (Container-Bindings, Console-Commands, …).
     * Commands ohne runningInConsole()-Wächter: die Admin-Oberfläche stößt sie per
     * Artisan::call/queue im Web-Prozess an (UI-Fuzz 2026-09-21).
     */
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
