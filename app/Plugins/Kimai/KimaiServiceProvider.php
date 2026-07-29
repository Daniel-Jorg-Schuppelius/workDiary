<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Kimai;

use App\Plugins\Kimai\Services\KimaiOutboxDispatcher;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (geladen vom Core-PluginServiceProvider, sobald
 * KimaiPlugin in der Registry steht). Registriert den Import-Service, lädt Routen
 * und Views sowie den Rückkanal für importierte Zeiten.
 */
class KimaiServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . KimaiPlugin::ID);

        $this->app->singleton(KimaiImportService::class, fn (): KimaiImportService => new KimaiImportService);
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'kimai');
        $this->app->make(IntegrationOutboxDispatcherResolver::class)->register(new KimaiOutboxDispatcher);
    }
}
