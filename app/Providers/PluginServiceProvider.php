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
use App\Plugins\Lexoffice\LexofficeMapper;
use App\Plugins\Lexoffice\LexofficeService;
use App\Plugins\PluginManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/plugins.php', 'plugins');

        // Lexoffice service: built lazily so it works even without a key set.
        $this->app->singleton(LexofficeService::class, function (): LexofficeService {
            return new LexofficeService(
                apiKey: config('plugins.lexoffice.api_key'),
                mapper: new LexofficeMapper,
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
                    throw new RuntimeException($class.' must implement '.Plugin::class);
                }
                $manager->register($instance);
            }

            return $manager;
        });
    }

    public function boot(): void
    {
        // Reserved for plugin-driven view composers, route loading, etc.
    }
}
