<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport;

use App\Plugins\RemoteSupport\Console\SyncSessionsCommand;
use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider. Wird vom Core-{@see \App\Providers\PluginServiceProvider}
 * geladen, sobald RemoteSupportPlugin in der Registry steht. Registriert den
 * Service, lädt Routes + Views und stellt den Sync-Command bereit.
 */
class RemoteSupportServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return RemoteSupportPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->singleton(RemoteSupportService::class, fn(): RemoteSupportService => new RemoteSupportService);
    }

    protected function bootPlugin(): void {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncSessionsCommand::class,
            ]);
        }
    }
}
