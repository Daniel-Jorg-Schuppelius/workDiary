<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Clockify;

use App\Plugins\Clockify\Services\ClockifyOutboxDispatcher;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (geladen vom Core-PluginServiceProvider, sobald
 * ClockifyPlugin in der Registry steht). Registriert den Import-Service, lädt
 * Routen und Views.
 */
class ClockifyServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . ClockifyPlugin::ID);

        $this->app->singleton(ClockifyImportService::class, fn (): ClockifyImportService => new ClockifyImportService);
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'clockify');
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new ClockifyOutboxDispatcher);
    }
}
