<?php

namespace App\Providers;

use App\Plugins\Contracts\Plugin;
use App\Plugins\PluginManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class PluginServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../../config/plugins.php', 'plugins');

        // Lexoffice service: built lazily so it works even without a key set.
        $this->app->singleton(\App\Plugins\Lexoffice\LexofficeService::class, function (): \App\Plugins\Lexoffice\LexofficeService {
            return new \App\Plugins\Lexoffice\LexofficeService(
                apiKey: config('plugins.lexoffice.api_key'),
                mapper: new \App\Plugins\Lexoffice\LexofficeMapper,
                defaults: [
                    'default_currency' => config('plugins.lexoffice.default_currency'),
                    'default_tax_type' => config('plugins.lexoffice.default_tax_type'),
                    'default_vat_rate' => config('plugins.lexoffice.default_vat_rate'),
                ],
            );
        });

        $this->app->singleton(PluginManager::class, function (Application $app): PluginManager {
            $manager = new PluginManager;

            /** @var array<int, class-string> $classes */
            $classes = (array) config('plugins.classes', []);
            foreach ($classes as $class) {
                if (! class_exists($class)) {
                    throw new RuntimeException("Plugin class {$class} not found");
                }
                $instance = $app->make($class);
                if (! $instance instanceof Plugin) {
                    throw new RuntimeException($class . ' must implement ' . Plugin::class);
                }
                $manager->register($instance);
            }

            return $manager;
        });
    }

    public function boot(): void {
        // Reserved for plugin-driven view composers, route loading, etc.
    }
}
