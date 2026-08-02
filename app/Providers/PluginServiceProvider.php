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

use App\Plugins\{CapabilityRegistry, PluginDiscovery, PluginErrorRecorder, PluginManager, PluginSchemaManager};
use App\Plugins\Contracts\Plugin;
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

        // Plugin-Klassen per Auto-Discovery (app/Plugins/*/*Plugin.php) plus optionaler Config-Liste — ein neues
        // Plugin braucht keinen manuellen Eintrag in config/plugins.php ({@see PluginDiscovery}).
        $classes = PluginDiscovery::classes();

        // Plugin-Service-Provider VOR dem PluginManager registrieren, damit Container-Bindings (z. B. LexofficeService)
        // verfügbar sind, sobald ein Plugin instanziiert wird. Konvention: Plugin-Klasse exponiert SERVICE_PROVIDER (Provider-FQCN).
        foreach ($classes as $class) {
            $this->registerPluginProvider($class);
        }

        // Capability-Registry (Review 2026-08, W5e): Kern-Enum + extern
        // registrierte Fähigkeiten. Muss vor den Plugin-Providern stehen,
        // damit diese in register() eigene Capabilities beisteuern können.
        $this->app->singleton(CapabilityRegistry::class);

        $this->app->singleton(PluginManager::class, function (Application $app) use ($classes): PluginManager {
            $manager = new PluginManager;

            foreach ($classes as $class) {
                $instance = $this->instantiatePlugin($app, $class);
                if ($instance === null) {
                    continue;
                }
                try {
                    $manager->register($instance);
                } catch (Throwable $e) {
                    // Duplikat-ID o. Ä. darf nie die ganze App reißen: Plugin
                    // überspringen und den Konflikt org-los aufzeichnen (W0b).
                    $this->safeRecord($instance->id(), 'boot', $e);
                }
            }

            return $manager;
        });
    }

    /** Auto-Upgrade nur einmal pro Prozess (Review 2026-08, D10/W6). */
    private static bool $schemaChecked = false;

    public function boot(): void {
        // Defensiv: Auto-Schema-Upgrade nur lokal — einmal pro Prozess, unter
        // dem Lock des SchemaManagers. In Produktion zeigt die Admin-Übersicht
        // ausstehende Upgrades mit Auslöse-Button (admin.plugins.upgrade).
        if (! $this->app->environment('local') || self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

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
            $this->safeRecord($this->pluginIdFor($class), 'boot', $e);
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
            $this->safeRecord($this->pluginIdFor($class), 'boot', $e);

            return null;
        }
    }

    /**
     * Plugin-ID für die Fehleraufzeichnung: Auto-Disable und Admin-UI matchen
     * gegen `Plugin::id()`, nicht den FQCN (W0a). Alle Plugins exponieren die
     * Konvention `const ID`; nur wenn sie fehlt, bleibt der FQCN als Notnagel.
     */
    private function pluginIdFor(string $class): string {
        try {
            if (defined("{$class}::ID") && is_string($class::ID) && $class::ID !== '') {
                return $class::ID;
            }
        } catch (Throwable) {
            // defined() kann bei kaputten Autoload-Ständen werfen → Fallback.
        }

        return $class;
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
