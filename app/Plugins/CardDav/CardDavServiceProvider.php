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
use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Bauturbo A9). Bindet die Gateway-Factory
 * (Tests ersetzen sie durch eine Fake-Variante ohne HTTP), registriert
 * Config-Defaults, Routen, Views und den Sync-Command.
 */
class CardDavServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return CardDavPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(CardDavGatewayFactory::class, LibCardDavGatewayFactory::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\CardDavSyncCommand::class,
            ]);
        }
    }
}
