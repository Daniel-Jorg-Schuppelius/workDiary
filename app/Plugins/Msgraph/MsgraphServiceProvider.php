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
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider (MVP-328, Bauturbo A8). Registriert
 * Config-Defaults, Routen, Views und den Publish-Command;
 * {@see MsgraphOAuth} ist Singleton — Tests ersetzen ihn durch eine Variante
 * mit Guzzle-MockHandler (Todoist-Muster).
 */
class MsgraphServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . MsgraphPlugin::ID);

        $this->app->singleton(MsgraphOAuth::class, fn(): MsgraphOAuth => new MsgraphOAuth());

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\MsgraphPublishCommand::class,
            ]);
        }
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'msgraph');
    }
}
