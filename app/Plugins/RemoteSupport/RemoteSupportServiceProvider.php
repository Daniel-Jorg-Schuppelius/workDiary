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

use App\Plugins\RemoteSupport\Console\{RetagEntriesCommand, SyncSessionsCommand};
use App\Plugins\RemoteSupport\Services\{RemoteDeviceRegistry, RemotePendingAssignmentService, RemoteSessionImporter, RemoteSupportGroupBooker};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Integration\InboxGroupBookerRegistry;

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
        $this->app->singleton(RemoteDeviceRegistry::class);
        $this->app->singleton(RemoteSessionImporter::class);
        $this->app->singleton(RemotePendingAssignmentService::class);
    }

    protected function bootPlugin(): void {
        // Tätigkeitsrecherche: Suchquelle und Indexpflege der Sitzungen (MVP-1045).
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Search\Indexing\Sources\SearchSource::class, \App\Plugins\RemoteSupport\Search\RemoteSessionSource::class);
        \App\Plugins\RemoteSupport\Models\RemotePendingSession::observe(\App\Observers\SearchIndexObserver::class);
        // Erweiterungspunkt des Moduls (MVP-1045): CSV-Import der Sitzungen.
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Import\EntitySpec::class, \App\Plugins\RemoteSupport\Import\RemoteSessionSpec::class);
        // Gruppierte Auflösung der Import-Inbox (MVP-1030).
        $this->app->make(InboxGroupBookerRegistry::class)->register(RemoteSupportPlugin::ID, RemoteSupportGroupBooker::class);

        $this->commands([
            SyncSessionsCommand::class,
            RetagEntriesCommand::class,
        ]);
    }
}
