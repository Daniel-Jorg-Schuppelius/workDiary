<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Plugins\Toggl\Console\TogglImportCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider. Wird vom Core-{@see \App\Providers\PluginServiceProvider}
 * geladen, sobald TogglPlugin in der Registry steht. Registriert den Service,
 * lädt Routes + Views und stellt den Import-Command bereit.
 */
class TogglServiceProvider extends ServiceProvider {
    public function register(): void {
        // Plugin liefert seine eigenen Config-Defaults/ENV-Fallbacks → `config('plugins.toggl.*')`.
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . TogglPlugin::ID);

        $this->app->singleton(TogglImportService::class, fn(): TogglImportService => new TogglImportService);
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'toggl');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TogglImportCommand::class,
            ]);
        }
    }
}
