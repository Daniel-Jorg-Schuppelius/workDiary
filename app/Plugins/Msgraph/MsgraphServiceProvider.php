<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph;

use App\Plugins\Msgraph\Api\MsgraphOAuth;
use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (MVP-328, Bauturbo A8). Registriert
 * Config-Defaults, Routen, Views und den Publish-Command;
 * {@see MsgraphOAuth} ist Singleton — Tests ersetzen ihn durch eine Variante
 * mit Guzzle-MockHandler (Todoist-Muster).
 */
class MsgraphServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return MsgraphPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(MsgraphOAuth::class, fn(): MsgraphOAuth => new MsgraphOAuth());

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MsgraphPublishCommand::class,
            ]);
        }
    }
}
