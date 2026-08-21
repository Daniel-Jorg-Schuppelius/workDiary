<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\CalDav;

use App\Plugins\CalDav\Contracts\CalDavGatewayFactory;
use App\Plugins\CalDav\Services\GuzzleCalDavGatewayFactory;
use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 058). Bindet die Gateway-Factory
 * (Tests ersetzen sie durch eine Fake-Variante ohne HTTP), registriert
 * Config-Defaults, Routen, Views und den Publish-Command.
 */
class CalDavServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return CalDavPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(CalDavGatewayFactory::class, GuzzleCalDavGatewayFactory::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\CalDavPublishCommand::class,
                Console\CalDavImportCommand::class,
            ]);
        }
    }
}
