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

use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (geladen vom Core-PluginServiceProvider, sobald
 * KimaiPlugin in der Registry steht). Registriert den Import-Service, lädt Routen
 * und Views.
 */
class KimaiServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . KimaiPlugin::ID);

        $this->app->singleton(KimaiImportService::class, fn (): KimaiImportService => new KimaiImportService);
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'kimai');
    }
}
