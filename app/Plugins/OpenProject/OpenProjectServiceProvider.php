<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject;

use App\Plugins\OpenProject\Console\{OpenProjectPushCommand, OpenProjectSyncCommand};
use App\Plugins\OpenProject\Services\{OpenProjectExportService, OpenProjectImportService, OpenProjectStructureSync};
use Illuminate\Support\ServiceProvider;

/**
 * Plugin-eigener ServiceProvider. Wird vom Core-{@see \App\Providers\PluginServiceProvider}
 * geladen, sobald OpenProjectPlugin in der Registry steht. Registriert die
 * Services, lädt Routes + Views, stellt die Migrationen und die Sync-/Push-
 * Commands bereit.
 */
class OpenProjectServiceProvider extends ServiceProvider {
    public function register(): void {
        // Plugin liefert seine eigenen Config-Defaults/ENV-Fallbacks (statt eines
        // zentralen Blocks in config/plugins.php) → `config('plugins.openproject.*')`.
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . OpenProjectPlugin::ID);

        $this->app->singleton(OpenProjectStructureSync::class, fn(): OpenProjectStructureSync => new OpenProjectStructureSync);
        $this->app->singleton(OpenProjectImportService::class, fn($app): OpenProjectImportService => new OpenProjectImportService($app->make(OpenProjectStructureSync::class)));
        $this->app->singleton(OpenProjectExportService::class, fn($app): OpenProjectExportService => new OpenProjectExportService($app->make(OpenProjectStructureSync::class)));
    }

    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/Resources/views', 'openproject');

        if ($this->app->runningInConsole()) {
            $this->commands([
                OpenProjectSyncCommand::class,
                OpenProjectPushCommand::class,
            ]);
        }
    }
}
