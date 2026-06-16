<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Providers;

use App\Plugins\Contracts\Plugin;
use App\Plugins\{PluginDiscovery, PluginErrorRecorder, PluginManager, PluginSchemaManager};
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Core-Side des Plugin-Systems. Lädt alle in config/plugins.php deklarierten
 * Plugin-Klassen und ihre zugehörigen Plugin-eigenen ServiceProvider.
 *
 * Boot-Isolation: Fehler beim Registrieren oder Instanziieren eines einzelnen
 * Plugins werden gefangen und über {@see PluginErrorRecorder} persistiert,
 * damit ein defektes Plugin die App nicht reißt. Nach {@see config('plugins.auto_disable_threshold')}
 * aufeinanderfolgenden Fehlern wird das Plugin automatisch deaktiviert.
 *
 * Aktiviert / deaktiviert wird ein Plugin pro Organisation über die
 * plugin_settings-Tabelle (s. /admin/plugins) plus globaler Auto-Disable.
 */
class PluginServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../../config/plugins.php', 'plugins');

        $this->app->singleton(PluginErrorRecorder::class);
        $this->app->singleton(PluginSchemaManager::class);

        // Plugin-Klassen per Auto-Discovery (app/Plugins/*/*Plugin.php) plus
        // optionaler expliziter Config-Liste — ein neues Plugin braucht keinen
        // manuellen Eintrag mehr in config/plugins.php (s. {@see PluginDiscovery}).
        $classes = PluginDiscovery::classes();

        // Plugin-Service-Provider VOR dem PluginManager registrieren, damit
        // Container-Bindings (z. B. LexofficeService) verfügbar sind, sobald
        // ein Plugin instanziiert wird. Konvention: Plugin-Klasse exponiert
        // eine SERVICE_PROVIDER-Konstante mit dem Provider-FQCN.
        foreach ($classes as $class) {
            $this->registerPluginProvider($class);
        }

        $this->app->singleton(PluginManager::class, function (Application $app) use ($classes): PluginManager {
            $manager = new PluginManager;

            foreach ($classes as $class) {
                $instance = $this->instantiatePlugin($app, $class);
                if ($instance !== null) {
                    $manager->register($instance);
                }
            }

            return $manager;
        });
    }

    public function boot(): void {
        // Defensiv: Auto-Schema-Upgrade nur in lokaler Umgebung. In Produktion
        // wird der Admin per UI-Hinweis darauf aufmerksam gemacht und löst
        // `php artisan plugin:upgrade` bewusst manuell aus.
        if (! $this->app->environment('local')) {
            return;
        }

        try {
            /** @var PluginManager $manager */
            $manager = $this->app->make(PluginManager::class);
            /** @var PluginSchemaManager $schema */
            $schema = $this->app->make(PluginSchemaManager::class);
        } catch (Throwable $e) {
            Log::warning('Could not resolve PluginManager in boot()', ['exception' => $e::class, 'message' => $e->getMessage()]);

            return;
        }

        foreach ($manager->all() as $plugin) {
            try {
                if ($schema->needsUpgrade($plugin)) {
                    $schema->upgrade($plugin);
                    Log::info('Auto-upgraded plugin schema (local env)', [
                        'plugin_id' => $plugin->id(),
                        'version' => $plugin->schemaVersion(),
                    ]);
                }
            } catch (Throwable $e) {
                $this->safeRecord($plugin->id(), 'boot', $e);
            }
        }
    }

    private function registerPluginProvider(string $class): void {
        try {
            if (! class_exists($class)) {
                Log::warning("Plugin class {$class} not found, skipping");

                return;
            }
            if (! defined("{$class}::SERVICE_PROVIDER")) {
                return;
            }
            /** @var class-string|null $providerFqcn */
            $providerFqcn = $class::SERVICE_PROVIDER;
            if (is_string($providerFqcn) && class_exists($providerFqcn)) {
                $this->app->register($providerFqcn);
            }
        } catch (Throwable $e) {
            $this->safeRecord($class, 'boot', $e);
        }
    }

    /**
     * @param  class-string  $class
     */
    private function instantiatePlugin(Application $app, string $class): ?Plugin {
        try {
            /** @var object $instance */
            $instance = $app->make($class);
            if (! $instance instanceof Plugin) {
                Log::warning($class . ' must implement ' . Plugin::class . ' — skipped');

                return null;
            }

            return $instance;
        } catch (Throwable $e) {
            $this->safeRecord($class, 'boot', $e);

            return null;
        }
    }

    private function safeRecord(string $pluginId, string $phase, Throwable $e): void {
        try {
            /** @var PluginErrorRecorder $recorder */
            $recorder = $this->app->make(PluginErrorRecorder::class);
            $recorder->record($pluginId, $phase, $e);
        } catch (Throwable $logFail) {
            // Letzter Strohhalm: nur loggen, niemals werfen — wir sind im Boot.
            Log::error('Failed to record plugin error', [
                'plugin_id' => $pluginId,
                'phase' => $phase,
                'original_exception' => $e::class,
                'original_message' => $e->getMessage(),
                'recorder_exception' => $logFail::class,
                'recorder_message' => $logFail->getMessage(),
            ]);
        }
    }
}
