<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\CardDav;

use App\Plugins\CardDav\Contracts\CardDavGatewayFactory;
use App\Plugins\CardDav\Services\LibCardDavGatewayFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (Bauturbo A9). Bindet die Gateway-Factory
 * (Tests ersetzen sie durch eine Fake-Variante ohne HTTP), registriert
 * Config-Defaults, Routen, Views und den Sync-Command.
 */
class CardDavServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . CardDavPlugin::ID);

        $this->app->singleton(CardDavGatewayFactory::class, LibCardDavGatewayFactory::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\CardDavSyncCommand::class,
            ]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'carddav');
    }
}
